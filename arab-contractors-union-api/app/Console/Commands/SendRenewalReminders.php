<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Notifications\MembershipExpiryReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * تذكير يومي بتجديد العضوية على محطات ثابتة قبل الانتهاء (30/14/7/3/1 يوم).
 * التشغيل اليومي + مطابقة تاريخ الانتهاء بالضبط = تذكير واحد لكل محطة بلا حالة إضافية.
 */
class SendRenewalReminders extends Command
{
    protected $signature = 'memberships:send-renewal-reminders {--days=30,14,7,3,1}';

    protected $description = 'إرسال تذكيرات تجديد العضوية للمقاولين قبل انتهاء اشتراكهم';

    public function handle(): int
    {
        $milestones = collect(explode(',', $this->option('days')))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0);

        $sent = 0;
        $skipped = 0;

        foreach ($milestones as $days) {
            $memberships = Membership::with('contractor')
                ->where('status', 'active')
                ->whereDate('expires_at', today()->addDays($days))
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
                    $skipped++;

                    continue;
                }

                // حماية من التكرار عند إعادة التشغيل يدوياً في نفس اليوم
                $dedupeKey = "renewal_reminder.{$membership->id}.{$days}." . today()->toDateString();
                if (! \Illuminate\Support\Facades\Cache::add($dedupeKey, true, now()->addDay())) {
                    continue;
                }

                $contractor->notify(new MembershipExpiryReminderNotification($membership, $days));
                $sent++;
            }
        }

        Log::channel('reminders')->info('renewal_reminders.run', [
            'sent'    => $sent,
            'skipped_pending_payment' => $skipped,
            'milestones' => $milestones->all(),
        ]);

        $this->info("أُرسل {$sent} تذكيراً، وتم تخطي {$skipped} (دفعة تجديد معلّقة).");

        return self::SUCCESS;
    }
}
