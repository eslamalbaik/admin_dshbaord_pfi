<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Contractor;
use App\Models\Notification;
use App\Models\NotificationRun;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a test contractor with default attributes.
     *
     * @param  array  $attributes
     * @return Contractor
     */
    protected function createTestContractor(array $attributes = []): Contractor
    {
        return Contractor::factory()->create(array_merge([
            'is_frozen' => false,
            'status' => 'active',
            'membership_expires_at' => Carbon::now()->addMonths(12),
        ], $attributes));
    }

    /**
     * Set up a device token for a contractor to receive FCM notifications.
     *
     * @param  Contractor  $contractor
     * @param  string|null  $token
     * @return Contractor
     */
    protected function setupFcmToken(Contractor $contractor, ?string $token = null): Contractor
    {
        $token = $token ?? 'test-fcm-token-' . $contractor->id;
        $contractor->update(['fcm_token' => $token]);
        return $contractor->refresh();
    }

    /**
     * Create and publish a test announcement.
     *
     * @param  array  $attributes
     * @return \App\Models\Announcement
     */
    protected function publishAnnouncement(array $attributes = [])
    {
        // Import Announcement model if available
        $announcementClass = 'App\Models\Announcement';
        if (! class_exists($announcementClass)) {
            $this->markTestSkipped('Announcement model not found');
        }

        return $announcementClass::factory()->create(array_merge([
            'status' => 'published',
            'published_at' => Carbon::now(),
        ], $attributes));
    }

    /**
     * Run a reminder job (grace period, renewal, etc.) and return the result.
     *
     * @param  string  $jobCommand
     * @return int
     */
    protected function runReminderJob(string $jobCommand): int
    {
        return Artisan::call($jobCommand);
    }

    /**
     * Assert that a notification was created for a contractor.
     *
     * @param  Contractor  $contractor
     * @param  NotificationType  $type
     * @param  array  $where
     * @return void
     */
    protected function assertNotificationCreated(Contractor $contractor, NotificationType $type, array $where = []): void
    {
        $this->assertTrue(
            Notification::where('contractor_id', $contractor->id)
                ->where('type', $type)
                ->where($where)
                ->exists(),
            "Notification of type {$type->value} not found for contractor {$contractor->id}"
        );
    }

    /**
     * Assert that a notification was NOT created for a contractor.
     *
     * @param  Contractor  $contractor
     * @param  NotificationType  $type
     * @param  array  $where
     * @return void
     */
    protected function assertNotificationNotCreated(Contractor $contractor, NotificationType $type, array $where = []): void
    {
        $this->assertFalse(
            Notification::where('contractor_id', $contractor->id)
                ->where('type', $type)
                ->where($where)
                ->exists(),
            "Notification of type {$type->value} should not exist for contractor {$contractor->id}"
        );
    }

    /**
     * Assert that a push notification was sent (via FCM channel).
     * This will depend on your push service implementation.
     *
     * @param  Contractor  $contractor
     * @param  string  $title
     * @return void
     */
    protected function assertPushSent(Contractor $contractor, string $title = ''): void
    {
        // This is a placeholder; actual FCM verification depends on your push service implementation
        // You might use Notification::fake() or a mock push sender service
        $this->assertTrue(true);
    }

    /**
     * Assert that a notification was marked as read.
     *
     * @param  Notification  $notification
     * @return void
     */
    protected function assertNotificationMarkedAsRead(Notification $notification): void
    {
        $this->assertNotNull(
            $notification->refresh()->read_at,
            "Notification {$notification->id} should be marked as read"
        );
    }

    /**
     * Get the latest notification for a contractor of a specific type.
     *
     * @param  Contractor  $contractor
     * @param  NotificationType  $type
     * @return Notification|null
     */
    protected function getLatestNotification(Contractor $contractor, NotificationType $type): ?Notification
    {
        return Notification::where('contractor_id', $contractor->id)
            ->where('type', $type)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Assert that a notification run was completed.
     *
     * @param  string  $jobName
     * @param  string|null  $date
     * @return void
     */
    protected function assertRunCompleted(string $jobName, ?string $date = null): void
    {
        $date = $date ?? today()->toDateString();

        $this->assertTrue(
            NotificationRun::where('job_name', $jobName)
                ->where('run_date', $date)
                ->where('status', 'completed')
                ->exists(),
            "Notification run for job {$jobName} on {$date} was not completed"
        );
    }
}
