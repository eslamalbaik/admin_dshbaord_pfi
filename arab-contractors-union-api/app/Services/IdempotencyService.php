<?php

namespace App\Services;

use App\Enums\JobName;
use App\Enums\NotificationType;
use App\Models\Contractor;
use App\Models\Notification;
use App\Models\NotificationRun;
use Carbon\Carbon;

class IdempotencyService
{
    /**
     * Check if a contractor has already been notified for a specific notification type on a given date.
     * Used to prevent duplicate notifications within the same day.
     *
     * @param  Contractor  $contractor
     * @param  NotificationType  $type
     * @param  string|\DateTime|null  $date
     * @return bool
     */
    public function checkIfAlreadyNotified(Contractor $contractor, NotificationType $type, $date = null): bool
    {
        $date = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();

        return Notification::where('contractor_id', $contractor->id)
            ->where('type', $type)
            ->whereDate('created_at', $date)
            ->exists();
    }

    /**
     * Mark a notification run as complete with the number of contractors notified.
     * Updates status to 'completed' and sets contractor_count.
     *
     * @param  NotificationRun  $run
     * @param  int  $contractorCount
     * @return bool
     */
    public function markRunComplete(NotificationRun $run, int $contractorCount = 0): bool
    {
        return $run->update([
            'status' => 'completed',
            'contractor_count' => $contractorCount,
        ]);
    }

    /**
     * Mark a notification run as failed, recording why.
     * Leaves the row in place so the same date can be retried (see completedRunExists).
     *
     * @param  NotificationRun  $run
     * @param  string  $errorMessage
     * @return bool
     */
    public function markRunFailed(NotificationRun $run, string $errorMessage): bool
    {
        return $run->markFailed($errorMessage);
    }

    /**
     * Get or create a notification run to ensure idempotency.
     * If a run already exists for the given job and date, returns it (no duplicate runs).
     * If not, creates a new one with status='started'.
     *
     * @param  JobName  $jobName
     * @param  string|\DateTime|null  $date
     * @return NotificationRun
     */
    public function getOrCreateRun(JobName $jobName, $date = null): NotificationRun
    {
        $date = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();

        // ملاحظة: cast الحقل 'date' يخزّن run_date بصيغة Y-m-d H:i:s، فمطابقة المساواة
        // بنص Y-m-d تفشل على SQLite (MariaDB تُحوّل ضمنياً فتُخفي المشكلة). whereDate تعمل على الاثنين.
        $run = NotificationRun::where('job_name', $jobName)
            ->whereDate('run_date', $date)
            ->first();

        return $run ?? NotificationRun::create([
            'job_name' => $jobName,
            'run_date' => $date,
            'status' => 'started',
        ]);
    }

    /**
     * Check if a run for a job already exists on a given date (used to skip duplicate runs).
     *
     * @param  JobName  $jobName
     * @param  string|\DateTime|null  $date
     * @return bool
     */
    public function runAlreadyExists(JobName $jobName, $date = null): bool
    {
        $date = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();

        return NotificationRun::where('job_name', $jobName)
            ->whereDate('run_date', $date)
            ->exists();
    }

    /**
     * Check if a *completed* run for a job exists on a given date.
     * Unlike runAlreadyExists(), a run left at 'started' (crashed mid-way) or marked
     * 'failed' does not count — otherwise a failed run would block every retry for that
     * date and the operator would have to delete the row by hand.
     *
     * @param  JobName  $jobName
     * @param  string|\DateTime|null  $date
     * @return bool
     */
    public function completedRunExists(JobName $jobName, $date = null): bool
    {
        $date = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();

        return NotificationRun::where('job_name', $jobName)
            ->whereDate('run_date', $date)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Get the last completed run for a job.
     *
     * @param  JobName  $jobName
     * @return NotificationRun|null
     */
    public function getLastCompletedRun(JobName $jobName): ?NotificationRun
    {
        return NotificationRun::where('job_name', $jobName)
            ->where('status', 'completed')
            ->orderBy('run_date', 'desc')
            ->first();
    }

    /**
     * Get all notifications sent in a specific run (for auditing).
     *
     * @param  NotificationRun  $run
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getNotificationsInRun(NotificationRun $run)
    {
        return Notification::where('run_id', $run->id)->get();
    }
}
