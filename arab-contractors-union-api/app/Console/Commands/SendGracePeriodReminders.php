<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Notifications\MembershipGracePeriodReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * تذكيرات المقاولين خلال فترة السماح بعد انتهاء عضويتهم (بداية / منتصف / نهاية الفترة).
 * فترة السماح = شهرين من تاريخ انتهاء العضوية، منتهية بآخر يوم بالشهر الثاني
 * (عضوية تنتهي 31 ديسمبر → فترة سماح حتى 28/29 فبراير).
 * التشغيل اليومي + مطابقة تاريخ محطة بالضبط = تذكير واحد لكل محطة بلا حالة إضافية.
 */
class SendGracePeriodReminders extends Command
{
    protected $signature = 'memberships:send-grace-period-reminders';

    protected $description = 'إرسال تذكيرات فترة السماح للمقاولين بعد انتهاء عضويتهم مباشرة';

    public function handle(): int
    {
        $sent = 0;
        $skipped = 0;

        $expiredMemberships = Membership::with('contractor')
            ->where('status', 'active')
            ->whereDate('expires_at', '<', today())
            ->whereDate('expires_at', '>=', today()->subMonths(3)) // خارج هالمدى فترة السماح خلصت أصلاً
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

            if (today()->gt($graceEndsAt)) {
                continue; // فترة السماح خلصت أصلاً
            }

            $milestones = [
                'start'  => $expiresAt->copy()->addDay(),
                'middle' => $expiresAt->copy()->addDays((int) round($expiresAt->diffInDays($graceEndsAt) / 2)),
                'end'    => $graceEndsAt->copy()->subDays(3),
            ];

            foreach ($milestones as $stage => $date) {
                if (! today()->isSameDay($date)) {
                    continue;
                }

                $dedupeKey = "grace_reminder.{$membership->id}.{$stage}." . today()->toDateString();
                if (! Cache::add($dedupeKey, true, now()->addDay())) {
                    continue;
                }

                $contractor->notify(new MembershipGracePeriodReminderNotification($membership, $stage, $graceEndsAt));
                $sent++;
            }
        }

        Log::channel('reminders')->info('grace_period_reminders.run', [
            'sent'    => $sent,
            'skipped_pending_payment' => $skipped,
        ]);

        $this->info("أُرسل {$sent} تذكير فترة سماح، وتم تخطي {$skipped} (دفعة تجديد معلّقة).");

        return self::SUCCESS;
    }
}
