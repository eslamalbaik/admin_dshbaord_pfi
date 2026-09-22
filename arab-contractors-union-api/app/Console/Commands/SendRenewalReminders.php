<?php

namespace App\Console\Commands;

use App\Enums\JobName;
use App\Enums\NotificationType;
use App\Models\Membership;
use App\Models\Notification;
use App\Notifications\MembershipExpiryReminderNotification;
use App\Notifications\SubscriptionExpiryReminderNotification;
use App\Services\IdempotencyService;
use App\Support\NotificationHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * تذكير يومي بتجديد العضوية على محطات ثابتة قبل الانتهاء (30/14/7/3/1 يوم).
 * التشغيل اليومي + مطابقة تاريخ الانتهاء بالضبط = تذكير واحد لكل محطة بلا حالة إضافية.
 *
 * Also sends expiry reminders to contractors via app notification system within the reminder window.
 */
class SendRenewalReminders extends Command
{
    protected $signature = 'memberships:send-renewal-reminders {--days=30,14,7,3,1 : Milestone days} {--date= : Run date (Y-m-d format), defaults to today}';

    protected $description = 'إرسال تذكيرات تجديد العضوية للمقاولين قبل انتهاء اشتراكهم + app notification system reminders';

    public function __construct(private IdempotencyService $idempotencyService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $runDate = $this->option('date') ? date('Y-m-d', strtotime($this->option('date'))) : today()->toDateString();
            $today = Carbon::parse($runDate);

            Log::info('Starting renewal reminder job', ['run_date' => $runDate]);
            $this->info("Starting renewal reminders for {$runDate}");

            // Check if run already exists (idempotency for app notifications)
            if ($this->idempotencyService->runAlreadyExists(JobName::RENEWAL, $runDate)) {
                Log::info('Renewal run already exists for date', ['run_date' => $runDate]);
                $this->warn("Renewal reminder already ran for {$runDate}. Skipping to prevent duplicates.");
                return 0;
            }

            // Create notification run
            $run = $this->idempotencyService->getOrCreateRun(JobName::RENEWAL, $runDate);

            $milestones = collect(explode(',', $this->option('days')))
                ->map(fn ($d) => (int) trim($d))
                ->filter(fn ($d) => $d > 0);

            $membershipSent = 0;
            $membershipSkipped = 0;

            // ============================================================
            // Existing Membership Renewal Reminders
            // ============================================================
            foreach ($milestones as $days) {
                $memberships = Membership::with('contractor')
                    ->where('status', 'active')
                    ->whereDate('expires_at', $today->clone()->addDays($days))
                    ->get();

                foreach ($memberships as $membership) {
                    $contractor = $membership->contractor;

                    if (! $contractor || $contractor->status === 'suspended') {
                        continue;
                    }

                    // من لديه دفعة تجديد قيد المراجعة لا يُذكَّر
                    $hasPendingRenewal = $contractor->payments()
                        ->where('type', 'membership_fee')
                        ->where('status', 'pending')
                        ->exists();

                    if ($hasPendingRenewal) {
                        $membershipSkipped++;
                        continue;
                    }

                    // حماية من التكرار عند إعادة التشغيل يدوياً في نفس اليوم
                    $dedupeKey = "renewal_reminder.{$membership->id}.{$days}." . $today->toDateString();
                    if (! Cache::add($dedupeKey, true, now()->addDay())) {
                        continue;
                    }

                    $contractor->notify(new MembershipExpiryReminderNotification($membership, $days));
                    $membershipSent++;
                }
            }

            // ============================================================
            // New Expiry Reminder Notifications (App Notification System)
            // ============================================================
            $expiryReminderSent = 0;
            $expiryReminderFailed = 0;

            // Get reminder window from config
            $windowDays = config('notifications.renewal_warning_window_days', 7);

            $contractors = NotificationHelper::getContractorsApproachingRenewal($windowDays);

            Log::info('Found contractors approaching renewal', [
                'count' => $contractors->count(),
                'window_days' => $windowDays,
            ]);
            $this->info("Found {$contractors->count()} contractors within {$windowDays}-day renewal window");

            foreach ($contractors as $contractor) {
                try {
                    // Check if already notified today
                    if ($this->idempotencyService->checkIfAlreadyNotified($contractor, NotificationType::EXPIRY_REMINDER, $runDate)) {
                        Log::debug('Contractor already notified today', [
                            'contractor_id' => $contractor->id,
                            'run_date' => $runDate,
                        ]);
                        continue;
                    }

                    $expiryDate = $contractor->membership_expires_at;

                    // Send notification
                    $contractor->notify(new SubscriptionExpiryReminderNotification($contractor, $expiryDate));

                    // Create database record
                    Notification::create([
                        'contractor_id' => $contractor->id,
                        'type' => NotificationType::EXPIRY_REMINDER,
                        'title' => 'تحذير: انتهاء الاشتراك قريبا',
                        'body' => "اشتراكك ينتهي في {$expiryDate->format('Y-m-d')}. يرجى التجديد قبل انتهاء الصلاحية.",
                        'reference_type' => 'renewal',
                        'action_url' => '/contractor/renewal',
                        'run_id' => $run->id,
                    ]);

                    $expiryReminderSent++;

                    Log::debug('Expiry reminder sent', [
                        'contractor_id' => $contractor->id,
                        'expiry_date' => $expiryDate,
                        'run_id' => $run->id,
                    ]);
                } catch (\Exception $e) {
                    $expiryReminderFailed++;
                    Log::error('Failed to send expiry reminder', [
                        'contractor_id' => $contractor->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Failed to send reminder to contractor {$contractor->id}: {$e->getMessage()}");
                }
            }

            // Mark app notification run as completed
            $this->idempotencyService->markRunComplete($run, $expiryReminderSent);

            Log::channel('reminders')->info('renewal_reminders.run', [
                'membership_sent'    => $membershipSent,
                'membership_skipped_pending_payment' => $membershipSkipped,
                'expiry_reminder_sent' => $expiryReminderSent,
                'expiry_reminder_failed' => $expiryReminderFailed,
                'run_id' => $run->id,
                'milestones' => $milestones->all(),
            ]);

            $this->info("Membership reminders: {$membershipSent} sent, {$membershipSkipped} skipped");
            $this->info("Expiry reminders: {$expiryReminderSent} sent");
            if ($expiryReminderFailed > 0) {
                $this->warn("Failed to send {$expiryReminderFailed} expiry reminders");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Error in renewal reminder job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }
}
