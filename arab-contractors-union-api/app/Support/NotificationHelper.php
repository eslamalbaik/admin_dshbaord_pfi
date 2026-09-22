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
                $query->whereRaw('paid_amount < amount');
            })
            ->distinct('id')
            ->get();
    }

    /**
     * Get contractors approaching renewal (within the reminder window).
     * Criteria: active or suspended, not frozen, membership expires within window_days.
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
            ->whereBetween('membership_expires_at', [$now, $windowEnd])
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
