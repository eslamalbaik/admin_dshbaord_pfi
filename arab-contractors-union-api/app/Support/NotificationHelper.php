<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Contractor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class NotificationHelper
{
    /**
     * Get contractors eligible to receive an announcement notification.
     * Criteria: active, not frozen, has device token or will get in-app notification anyway.
     *
     * @param  Announcement  $announcement
     * @return Collection
     */
    public static function getEligibleContractorsForAnnouncement(Announcement $announcement): Collection
    {
        return Contractor::where('is_frozen', false)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get contractors with outstanding payment obligations (grace period).
     * Criteria: active or suspended, not frozen, has unpaid dues or penalties.
     *
     * @return Collection
     */
    public static function getContractorsWithOutstandingPayment(): Collection
    {
        return Contractor::where('is_frozen', false)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('dues', function ($query) {
                // أعمدة الذمم هي amount_jod/paid_jod/status — لا amount/paid_amount
                // (هذه للمخالفات penalties). partially_paid تُعامل كغير مسدَّدة.
                $query->whereIn('status', ['unpaid', 'partially_paid']);
            })
            ->distinct('id')
            ->get();
    }

    /**
     * Get contractors approaching renewal (within the reminder window).
     * Criteria: active or suspended, not frozen, membership expires within window_days.
     *
     * تاريخ الانتهاء مصدره memberships.expires_at للعضوية الفعّالة — لا يوجد عمود
     * membership_expires_at على جدول contractors (نفس المصدر الذي يستخدمه
     * SendRenewalReminders في كتلة تذكيرات المحطات).
     *
     * @param  int  $windowDays
     * @return Collection
     */
    public static function getContractorsApproachingRenewal(int $windowDays = 7): Collection
    {
        $now = Carbon::now();
        $windowEnd = $now->clone()->addDays($windowDays);

        return Contractor::where('is_frozen', false)
            ->whereIn('status', ['active', 'suspended'])
            ->whereHas('memberships', function ($query) use ($now, $windowEnd) {
                // expires_at عمود date — نقارن بالتاريخ فقط حتى لا تسقط عضوية تنتهي اليوم.
                $query->where('status', 'active')
                    ->whereBetween('expires_at', [$now->toDateString(), $windowEnd->toDateString()]);
            })
            ->with('activeMembership')
            ->get();
    }

    /**
     * Build a notification run record for logging/tracking.
     * (Actual creation handled by NotificationService::getOrCreateRun)
     *
     * @param  string  $jobName
     * @param  string|null  $runDate
     * @return array
     */
    public static function buildNotificationRun(string $jobName, ?string $runDate = null): array
    {
        return [
            'job_name' => $jobName,
            'run_date' => $runDate ?? today()->toDateString(),
            'status' => 'started',
        ];
    }
}
