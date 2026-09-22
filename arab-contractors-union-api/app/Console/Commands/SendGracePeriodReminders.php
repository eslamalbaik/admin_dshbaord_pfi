<?php

namespace App\Console\Commands;

use App\Enums\JobName;
use App\Enums\NotificationType;
use App\Models\Membership;
use App\Models\Notification;
use App\Notifications\MembershipGracePeriodReminderNotification;
use App\Notifications\SubscriptionPaymentReminderNotification;
use App\Services\IdempotencyService;
use App\Support\NotificationHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * تذكيرات المقاولين خلال فترة السماح بعد انتهاء عضويتهم (بداية / منتصف / نهاية الفترة).
 * فترة السماح = شهرين من تاريخ انتهاء العضوية، منتهية بآخر يوم بالشهر الثاني
 * (عضوية تنتهي 31 ديسمبر → فترة سماح حتى 28/29 فبراير).
 * التشغيل اليومي + مطابقة تاريخ محطة بالضبط = تذكير واحد لكل محطة بلا حالة إضافية.
 *
 * Also sends payment reminders to contractors with outstanding dues (app notification system).
 */
class SendGracePeriodReminders extends Command
{
    protected $signature = 'memberships:send-grace-period-reminders {--date= : Run date (Y-m-d format), defaults to today}';

    protected $description = 'إرسال تذكيرات فترة السماح للمقاولين بعد انتهاء عضويتهم مباشرة + reminders for outstanding dues';

    public function __construct(private IdempotencyService $idempotencyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $runDate = $this->option('date') ? date('Y-m-d', strtotime($this->option('date'))) : today()->toDateString();
            $today = \Carbon\Carbon::parse($runDate);

            Log::info('Starting grace period reminder job', ['run_date' => $runDate]);
            $this->info("Starting grace period reminders for {$runDate}");

            // Check if run already exists (idempotency for app notifications)
            if ($this->idempotencyService->runAlreadyExists(JobName::GRACE_PERIOD, $runDate)) {
                Log::info('Grace period run already exists for date', ['run_date' => $runDate]);
                $this->warn("Grace period reminder already ran for {$runDate}. Skipping to prevent duplicates.");
                return 0;
            }

            // Create notification run
            $run = $this->idempotencyService->getOrCreateRun(JobName::GRACE_PERIOD, $runDate);

            // ============================================================
            // Existing Membership Grace Period Reminders
            // ============================================================
            $membershipSent = 0;
            $skipped = 0;

            $expiredMemberships = Membership::with('contractor')
                ->where('status', 'active')
                ->whereDate('expires_at', '<', $today)
                ->whereDate('expires_at', '>=', $today->clone()->subMonths(3))
                ->get();

            foreach ($expiredMemberships as $membership) {
                $contractor = $membership->contractor;

                if (! $contractor || $contractor->status === 'suspended') {
                    continue;
                }

                $hasPendingRenewal = $contractor->payments()
                    ->where('type', 'membership_fee')
                    ->where('status', 'pending')
                    ->exists();

                if ($hasPendingRenewal) {
                    $skipped++;
                    continue;
                }

                $expiresAt = $membership->expires_at->copy()->startOfDay();
                $graceEndsAt = $expiresAt->copy()->addMonthsNoOverflow(2)->endOfMonth();

                if ($today->gt($graceEndsAt)) {
                    continue;
                }

                $milestones = [
                    'start'  => $expiresAt->copy()->addDay(),
                    'middle' => $expiresAt->copy()->addDays((int) round($expiresAt->diffInDays($graceEndsAt) / 2)),
                    'end'    => $graceEndsAt->copy()->subDays(3),
                ];

                foreach ($milestones as $stage => $date) {
                    if (! $today->isSameDay($date)) {
                        continue;
                    }

                    $dedupeKey = "grace_reminder.{$membership->id}.{$stage}." . $today->toDateString();
                    if (! Cache::add($dedupeKey, true, now()->addDay())) {
                        continue;
                    }

                    $contractor->notify(new MembershipGracePeriodReminderNotification($membership, $stage, $graceEndsAt));
                    $membershipSent++;
                }
            }

            // ============================================================
            // New Payment Reminder Notifications (App Notification System)
            // ============================================================
            $paymentReminderSent = 0;
            $paymentReminderFailed = 0;

            $contractors = NotificationHelper::getContractorsWithOutstandingPayment();

            Log::info('Found contractors with outstanding payment', ['count' => $contractors->count()]);
            $this->info("Found {$contractors->count()} contractors with outstanding payment");

            foreach ($contractors as $contractor) {
                try {
                    // Check if already notified today
                    if ($this->idempotencyService->checkIfAlreadyNotified($contractor, NotificationType::PAYMENT_REMINDER, $runDate)) {
                        Log::debug('Contractor already notified today', [
                            'contractor_id' => $contractor->id,
                            'run_date' => $runDate,
                        ]);
                        continue;
                    }

                    // Send notification
                    $contractor->notify(new SubscriptionPaymentReminderNotification($contractor));

                    // Create database record
                    Notification::create([
                        'contractor_id' => $contractor->id,
                        'type' => NotificationType::PAYMENT_REMINDER,
                        'title' => 'تذكير بدفع الاشتراك',
                        'body' => 'لديك اشتراك مستحق. يرجى الدفع الآن.',
                        'reference_type' => 'payment',
                        'action_url' => '/contractor/payment',
                        'run_id' => $run->id,
                    ]);

                    $paymentReminderSent++;

                    Log::debug('Payment reminder sent', [
                        'contractor_id' => $contractor->id,
                        'run_id' => $run->id,
                    ]);
                } catch (\Exception $e) {
                    $paymentReminderFailed++;
                    Log::error('Failed to send payment reminder', [
                        'contractor_id' => $contractor->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Failed to send reminder to contractor {$contractor->id}: {$e->getMessage()}");
                }
            }

            // Mark app notification run as completed
            $this->idempotencyService->markRunComplete($run, $paymentReminderSent);

            Log::channel('reminders')->info('grace_period_reminders.run', [
                'membership_sent'    => $membershipSent,
                'skipped_pending_payment' => $skipped,
                'payment_reminder_sent' => $paymentReminderSent,
                'payment_reminder_failed' => $paymentReminderFailed,
                'run_id' => $run->id,
            ]);

            $this->info("Membership reminders: {$membershipSent} sent, {$skipped} skipped");
            $this->info("Payment reminders: {$paymentReminderSent} sent");
            if ($paymentReminderFailed > 0) {
                $this->warn("Failed to send {$paymentReminderFailed} payment reminders");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error in grace period reminder job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }
}
