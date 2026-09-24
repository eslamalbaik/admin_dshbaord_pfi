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
        $runDate = $this->option('date') ? date('Y-m-d', strtotime($this->option('date'))) : today()->toDateString();
        $today = \Carbon\Carbon::parse($runDate);

        Log::info('Starting grace period reminder job', ['run_date' => $runDate]);
        $this->info("Starting grace period reminders for {$runDate}");

        // تخطٍّ فقط إذا اكتمل تشغيل اليوم فعلاً — التشغيل الفاشل/المتوقف يُعاد تشغيله
        if ($this->idempotencyService->completedRunExists(JobName::GRACE_PERIOD, $runDate)) {
            Log::info('Grace period run already completed for date', ['run_date' => $runDate]);
            $this->warn("Grace period reminder already ran for {$runDate}. Skipping to prevent duplicates.");
            return self::SUCCESS;
        }

        // Create notification run
        $run = $this->idempotencyService->getOrCreateRun(JobName::GRACE_PERIOD, $runDate);

        try {
            // ============================================================
            // Existing Membership Grace Period Reminders
            // ============================================================
            $membershipSent = 0;
            $skipped = 0;
            $membershipFailed = 0;

            $expiredMemberships = Membership::with('contractor')
                ->where('status', 'active')
                ->whereDate('expires_at', '<', $today)
                ->whereDate('expires_at', '>=', $today->clone()->subMonths(3))
                ->get();

            foreach ($expiredMemberships as $membership) {
                // فشل مقاول واحد لا يُسقِط التشغيل كله — يُسجَّل ويُعدّ ثم نكمل
                try {
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
                } catch (\Throwable $e) {
                    $membershipFailed++;
                    Log::channel('reminders')->error('grace_period_reminders.membership_failed', [
                        'membership_id' => $membership->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Failed to send grace period reminder for membership {$membership->id}: {$e->getMessage()}");
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
                } catch (\Throwable $e) {
                    $paymentReminderFailed++;
                    Log::channel('reminders')->error('grace_period_reminders.contractor_failed', [
                        'contractor_id' => $contractor->id,
                        'run_id' => $run->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Failed to send reminder to contractor {$contractor->id}: {$e->getMessage()}");
                }
            }

            // Mark app notification run as completed
            $this->idempotencyService->markRunComplete($run, $paymentReminderSent);

            $totalSent = $membershipSent + $paymentReminderSent;
            $totalFailed = $membershipFailed + $paymentReminderFailed;

            Log::channel('reminders')->log($totalFailed > 0 ? 'error' : 'info', 'grace_period_reminders.run', [
                'membership_sent'    => $membershipSent,
                'skipped_pending_payment' => $skipped,
                'membership_failed' => $membershipFailed,
                'payment_reminder_sent' => $paymentReminderSent,
                'payment_reminder_failed' => $paymentReminderFailed,
                'run_id' => $run->id,
            ]);

            $this->info("Membership reminders: {$membershipSent} sent, {$skipped} skipped");
            $this->info("Payment reminders: {$paymentReminderSent} sent");
            if ($totalFailed > 0) {
                $this->warn("Failed to send {$totalFailed} reminders ({$membershipFailed} membership, {$paymentReminderFailed} payment)");
            }

            // كل محاولة إرسال فشلت = عطل عام (لا مجرد مقاول واحد سيّئ) — أخرج بكود فشل
            // لتعمل onFailure في routes/console.php. فشل جزئي يبقى SUCCESS تفادياً لإنذار يومي كاذب.
            if ($totalFailed > 0 && $totalSent === 0) {
                Log::channel('reminders')->critical('grace_period_reminders.all_sends_failed', [
                    'run_id' => $run->id,
                    'run_date' => $runDate,
                    'failed' => $totalFailed,
                ]);

                return self::FAILURE;
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            // فشل إعداد/استعلام (لا فشل مقاول واحد): يُسجَّل بمستوى critical على قناة reminders
            // ثم يُعاد رميه — الأمر يخرج بكود غير صفري فتُشغَّل onFailure في routes/console.php.
            // لا تبتلع هذا الاستثناء: ابتلاعه سابقاً أخفى أعطال أعمدة SQL شهوراً.
            Log::channel('reminders')->critical('grace_period_reminders.failed', [
                'run_id' => $run->id,
                'run_date' => $runDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("Error: {$e->getMessage()}");

            // وسم التشغيل كفاشل يلمس قاعدة البيانات — وقد تكون هي العطل نفسه.
            // فشله هنا يجب ألا يحجب الاستثناء الأصلي.
            try {
                $this->idempotencyService->markRunFailed($run, $e->getMessage());
            } catch (\Throwable $markFailed) {
                Log::channel('reminders')->critical('grace_period_reminders.mark_failed_errored', [
                    'run_id' => $run->id,
                    'error' => $markFailed->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
