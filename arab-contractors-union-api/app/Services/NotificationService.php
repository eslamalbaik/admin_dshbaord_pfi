<?php

namespace App\Services;

use App\Enums\JobName;
use App\Enums\NotificationType;
use App\Models\Contractor;
use App\Models\Notification;
use App\Models\NotificationRun;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Get or create a notification run for idempotency.
     *
     * @param  JobName  $jobName
     * @param  \DateTime|string|null  $runDate
     * @return NotificationRun|null
     */
    public function getOrCreateRun(JobName $jobName, $runDate = null): ?NotificationRun
    {
        $runDate = $runDate ? \Carbon\Carbon::parse($runDate)->toDateString() : today()->toDateString();

        // Check if run already exists
        $existingRun = NotificationRun::where('job_name', $jobName)
            ->where('run_date', $runDate)
            ->first();

        if ($existingRun) {
            return $existingRun;
        }

        // Create new run
        return NotificationRun::create([
            'job_name' => $jobName,
            'run_date' => $runDate,
            'status' => 'started',
        ]);
    }

    /**
     * Check if a contractor has already been notified for a specific type on a given date.
     *
     * @param  Contractor  $contractor
     * @param  NotificationType  $type
     * @param  string|\DateTime|null  $date
     * @return bool
     */
    public function hasBeenNotifiedToday(Contractor $contractor, NotificationType $type, $date = null): bool
    {
        $date = $date ? \Carbon\Carbon::parse($date)->toDateString() : today()->toDateString();

        return Notification::where('contractor_id', $contractor->id)
            ->where('type', $type)
            ->whereDate('created_at', $date)
            ->exists();
    }

    /**
     * Create a notification record in the database.
     *
     * @param  Contractor  $contractor
     * @param  NotificationType  $type
     * @param  array<string, mixed>  $data
     * @return Notification
     */
    public function createNotificationRecord(Contractor $contractor, NotificationType $type, array $data): Notification
    {
        return Notification::create([
            'contractor_id' => $contractor->id,
            'type' => $type,
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'reference_id' => $data['reference_id'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'run_id' => $data['run_id'] ?? null,
        ]);
    }

    /**
     * Mark a notification run as completed.
     *
     * @param  NotificationRun  $run
     * @param  int  $contractorCount
     * @return bool
     */
    public function completeRun(NotificationRun $run, int $contractorCount = 0): bool
    {
        Log::info('Completing notification run', [
            'run_id' => $run->id,
            'job_name' => $run->job_name->value,
            'run_date' => $run->run_date,
            'contractor_count' => $contractorCount,
        ]);

        return $run->markCompleted($contractorCount);
    }

    /**
     * Mark a notification run as failed.
     *
     * @param  NotificationRun  $run
     * @param  string  $errorMessage
     * @return bool
     */
    public function failRun(NotificationRun $run, string $errorMessage): bool
    {
        Log::error('Notification run failed', [
            'run_id' => $run->id,
            'job_name' => $run->job_name->value,
            'run_date' => $run->run_date,
            'error' => $errorMessage,
        ]);

        return $run->markFailed($errorMessage);
    }

    /**
     * Get the number of unread notifications for a contractor.
     *
     * @param  Contractor  $contractor
     * @return int
     */
    public function getUnreadCount(Contractor $contractor): int
    {
        return Notification::where('contractor_id', $contractor->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get recent notification runs for a job.
     *
     * @param  JobName  $jobName
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentRuns(JobName $jobName, int $limit = 10)
    {
        return NotificationRun::where('job_name', $jobName)
            ->orderBy('run_date', 'desc')
            ->limit($limit)
            ->get();
    }
}
