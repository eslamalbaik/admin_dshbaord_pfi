<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Events\AnnouncementPublished;
use App\Models\Announcement;
use App\Models\Contractor;
use App\Models\Notification;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class AnnouncementNotificationTest extends NotificationTestCase
{
    /**
     * Test: Publishing an announcement sends notification to eligible active contractors.
     */
    public function test_published_announcement_sends_notification_to_eligible_contractor(): void
    {
        NotificationFacade::fake();
        Event::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);

        $announcement = $this->publishAnnouncement();

        // Dispatch the event
        event(new AnnouncementPublished($announcement));

        // Verify notification was sent
        NotificationFacade::assertSentTo($contractor, AnnouncementNotification::class);

        // Verify notification record was created in database
        $this->assertNotificationCreated($contractor, NotificationType::ANNOUNCEMENT, [
            'reference_id' => $announcement->id,
        ]);
    }

    /**
     * Test: Draft announcement does not send notification.
     */
    public function test_draft_announcement_does_not_send_notification(): void
    {
        NotificationFacade::fake();
        Event::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);

        // Create draft announcement (status != published)
        $announcement = Announcement::factory()->create([
            'status' => 'draft',
        ]);

        // Dispatch event (in real code, this would only fire on status change to published)
        event(new AnnouncementPublished($announcement));

        // Verify notification was NOT sent
        NotificationFacade::assertNotSentTo($contractor, AnnouncementNotification::class);

        // Verify no notification record
        $this->assertNotificationNotCreated($contractor, NotificationType::ANNOUNCEMENT);
    }

    /**
     * Test: Editing a published announcement does not resend notification.
     */
    public function test_editing_published_announcement_does_not_resend_notification(): void
    {
        NotificationFacade::fake();
        Event::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);

        // Create and publish announcement
        $announcement = $this->publishAnnouncement();
        event(new AnnouncementPublished($announcement));

        // Clear notifications
        Notification::truncate();
        NotificationFacade::reset();

        // Edit the announcement (event should NOT fire)
        $announcement->update(['title' => 'Updated Title']);

        // Verify no new notification was sent
        NotificationFacade::assertNothingSent();
    }

    /**
     * Test: Tapping announcement notification opens correct announcement.
     */
    public function test_announcement_notification_deep_link_opens_correct_announcement(): void
    {
        NotificationFacade::fake();
        Event::fake();

        $contractor = $this->createTestContractor();
        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        // Get the notification record
        $notification = $this->getLatestNotification($contractor, NotificationType::ANNOUNCEMENT);

        // Verify action_url points to correct announcement
        $this->assertEquals(
            "/contractor/announcements/{$announcement->id}",
            $notification->action_url
        );

        // Verify reference_id matches announcement
        $this->assertEquals($announcement->id, $notification->reference_id);
        $this->assertEquals('announcement', $notification->reference_type);
    }

    /**
     * Test: Contractor can mark announcement notification as read.
     */
    public function test_contractor_can_mark_announcement_notification_as_read(): void
    {
        NotificationFacade::fake();
        Event::fake();

        $contractor = $this->createTestContractor();
        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        $notification = $this->getLatestNotification($contractor, NotificationType::ANNOUNCEMENT);
        $this->assertNull($notification->read_at);

        // Mark as read
        $notification->markAsRead();

        // Verify it's marked as read
        $this->assertNotificationMarkedAsRead($notification);
    }

    /**
     * Test: Frozen contractor does not receive announcement notification.
     */
    public function test_frozen_contractor_does_not_receive_announcement_notification(): void
    {
        NotificationFacade::fake();
        Event::fake();

        $contractor = $this->createTestContractor(['is_frozen' => true]);
        $this->setupFcmToken($contractor);

        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        // Verify notification was NOT sent
        NotificationFacade::assertNotSentTo($contractor, AnnouncementNotification::class);

        // Verify no notification record
        $this->assertNotificationNotCreated($contractor, NotificationType::ANNOUNCEMENT);
    }

    /**
     * Test: Contractor without device token still gets in-app notification.
     */
    public function test_contractor_without_device_token_still_gets_in_app_notification(): void
    {
        NotificationFacade::fake();
        Event::fake();

        $contractor = $this->createTestContractor();
        // Don't set FCM token

        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        // Verify notification record was created (in-app)
        $this->assertNotificationCreated($contractor, NotificationType::ANNOUNCEMENT, [
            'reference_id' => $announcement->id,
        ]);

        // Verify no push was sent (since there's no device token)
        NotificationFacade::assertNotSentTo($contractor, AnnouncementNotification::class, function ($notification) {
            return in_array('fcm', $notification->via($contractor));
        });
    }
}
