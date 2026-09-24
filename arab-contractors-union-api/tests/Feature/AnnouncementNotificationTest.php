<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Events\AnnouncementPublished;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Laravel\Sanctum\Sanctum;

/**
 * توزيع إشعارات التعاميم عبر مستمع SendAnnouncementNotifications.
 *
 * لا نستخدم Event::fake() في الاختبارات التي تتحقق من أثر المستمع — تزييف ناقل
 * الأحداث يمنع تشغيله أصلاً. نزيّف ناقل الإشعارات فقط (سجلّ app_notifications
 * ينشئه المستمع مباشرة، فلا يتأثر بالتزييف).
 */
class AnnouncementNotificationTest extends NotificationTestCase
{
    /**
     * Test: Publishing an announcement sends notification to eligible active contractors.
     */
    public function test_published_announcement_sends_notification_to_eligible_contractor(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);

        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        NotificationFacade::assertSentTo($contractor, AnnouncementNotification::class);

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

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);

        // مسودة: is_published = false (الحالة الافتراضية للمصنع).
        $announcement = Announcement::factory()->create();

        // حتى لو أُطلق الحدث خطأً، الحارس في المستمع يمنع التوزيع.
        event(new AnnouncementPublished($announcement));

        NotificationFacade::assertNotSentTo($contractor, AnnouncementNotification::class);
        $this->assertNotificationNotCreated($contractor, NotificationType::ANNOUNCEMENT);
    }

    /**
     * Test: Scheduled (future) announcement does not send notification yet.
     */
    public function test_scheduled_announcement_does_not_send_notification_yet(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $announcement = Announcement::factory()->scheduled()->create();

        event(new AnnouncementPublished($announcement));

        NotificationFacade::assertNotSentTo($contractor, AnnouncementNotification::class);
        $this->assertNotificationNotCreated($contractor, NotificationType::ANNOUNCEMENT);
    }

    /**
     * Test: Editing an already-published announcement does not re-dispatch the event.
     */
    public function test_editing_published_announcement_does_not_resend_notification(): void
    {
        Event::fake([AnnouncementPublished::class]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $announcement = $this->publishAnnouncement();

        $this->putJson("/api/v1/admin/announcements/{$announcement->id}", [
            'title'        => 'عنوان مُحدَّث',
            'is_published' => true,
        ])->assertOk();

        // published_at موجود مسبقاً ⇒ ليس نشراً جديداً ⇒ لا حدث توزيع.
        Event::assertNotDispatched(AnnouncementPublished::class);
    }

    /**
     * Test: Tapping announcement notification opens correct announcement.
     */
    public function test_announcement_notification_deep_link_opens_correct_announcement(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        $notification = $this->getLatestNotification($contractor, NotificationType::ANNOUNCEMENT);
        $this->assertNotNull($notification);

        $this->assertSame("/contractor/announcements/{$announcement->id}", $notification->action_url);
        $this->assertSame($announcement->id, $notification->reference_id);
        $this->assertSame('announcement', $notification->reference_type);
    }

    /**
     * Test: Contractor can mark announcement notification as read.
     */
    public function test_contractor_can_mark_announcement_notification_as_read(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        $notification = $this->getLatestNotification($contractor, NotificationType::ANNOUNCEMENT);
        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);

        $notification->markAsRead();

        $this->assertNotificationMarkedAsRead($notification);
    }

    /**
     * Test: Frozen contractor does not receive announcement notification.
     */
    public function test_frozen_contractor_does_not_receive_announcement_notification(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor(['is_frozen' => true]);
        $this->setupFcmToken($contractor);

        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        NotificationFacade::assertNotSentTo($contractor, AnnouncementNotification::class);
        $this->assertNotificationNotCreated($contractor, NotificationType::ANNOUNCEMENT);
    }

    /**
     * Test: Contractor without device token still gets in-app notification.
     */
    public function test_contractor_without_device_token_still_gets_in_app_notification(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        // بدون fcm_token

        $announcement = $this->publishAnnouncement();

        event(new AnnouncementPublished($announcement));

        $this->assertNotificationCreated($contractor, NotificationType::ANNOUNCEMENT, [
            'reference_id' => $announcement->id,
        ]);

        NotificationFacade::assertSentTo(
            $contractor,
            AnnouncementNotification::class,
            function (AnnouncementNotification $notification) use ($contractor) {
                return ! in_array('fcm', $notification->via($contractor), true);
            }
        );
    }
}
