<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Notifications\SubscriptionExpiryReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * تذكير قرب انتهاء الاشتراك — يمرّ عبر أمر memberships:send-renewal-reminders،
 * وهو الجهة التي تنشئ سجلّ app_notifications (notify() وحده لا ينشئه).
 * نافذة التذكير الافتراضية 7 أيام (config/notifications.renewal_warning_window_days).
 */
class ExpiryReminderTest extends NotificationTestCase
{
    private const COMMAND = 'memberships:send-renewal-reminders';

    /**
     * Test: Contractor within renewal window receives expiry reminder with correct date.
     */
    public function test_contractor_within_renewal_window_receives_expiry_reminder(): void
    {
        NotificationFacade::fake();

        $expiryDate = Carbon::now()->addDays(5);
        $contractor = $this->createTestContractor();
        $this->giveMembershipExpiring($contractor, $expiryDate);
        $this->setupFcmToken($contractor);

        $this->runReminderJob(self::COMMAND);

        NotificationFacade::assertSentTo($contractor, SubscriptionExpiryReminderNotification::class);

        $notification = $this->getLatestNotification($contractor, NotificationType::EXPIRY_REMINDER);
        $this->assertNotNull($notification);
        $this->assertStringContainsString($expiryDate->format('Y-m-d'), $notification->body);
    }

    /**
     * Test: Contractor outside renewal window does not receive reminder.
     */
    public function test_contractor_outside_renewal_window_does_not_receive_reminder(): void
    {
        NotificationFacade::fake();

        // 20 يوماً: خارج نافذة الـ7 أيام وليست محطة تذكير (30/14/7/3/1).
        $contractor = $this->createTestContractor();
        $this->giveMembershipExpiring($contractor, Carbon::now()->addDays(20));
        $this->setupFcmToken($contractor);

        $this->runReminderJob(self::COMMAND);

        NotificationFacade::assertNotSentTo($contractor, SubscriptionExpiryReminderNotification::class);
        $this->assertNotificationNotCreated($contractor, NotificationType::EXPIRY_REMINDER);
    }

    /**
     * Test: Renewed contractor does not receive a further expiry reminder.
     */
    public function test_renewed_contractor_does_not_receive_duplicate_expiry_reminder(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $membership = $this->giveMembershipExpiring($contractor, Carbon::now()->addDays(5));
        $this->setupFcmToken($contractor);

        // التشغيل الأول: داخل النافذة → تذكير واحد.
        $this->runReminderJob(self::COMMAND);
        $this->assertSame(1, $this->countNotificationsToday($contractor, NotificationType::EXPIRY_REMINDER));

        // التجديد يدفع تاريخ الانتهاء خارج النافذة.
        $membership->update(['expires_at' => Carbon::now()->addMonths(12)]);

        // تشغيل بتاريخ آخر لتجاوز حارس "الرن موجود مسبقاً" — لا تذكير جديد.
        $this->runReminderJob(self::COMMAND, ['--date' => Carbon::now()->addDay()->toDateString()]);

        $this->assertSame(1, $this->countNotificationsToday($contractor, NotificationType::EXPIRY_REMINDER));
    }

    /**
     * Test: Expiry reminder is idempotent (same run date = no duplicate).
     */
    public function test_expiry_reminder_is_idempotent_same_day(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $this->giveMembershipExpiring($contractor, Carbon::now()->addDays(5));
        $this->setupFcmToken($contractor);

        $this->runReminderJob(self::COMMAND);
        $countAfterFirstRun = $this->countNotificationsToday($contractor, NotificationType::EXPIRY_REMINDER);

        // إعادة التشغيل بنفس التاريخ تُتخطّى عبر NotificationRun الموجود.
        $this->runReminderJob(self::COMMAND);
        $countAfterSecondRun = $this->countNotificationsToday($contractor, NotificationType::EXPIRY_REMINDER);

        $this->assertSame(1, $countAfterFirstRun);
        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }

    /**
     * Test: Frozen contractor does not receive expiry reminder.
     */
    public function test_frozen_contractor_does_not_receive_expiry_reminder(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor(['is_frozen' => true]);
        $this->giveMembershipExpiring($contractor, Carbon::now()->addDays(5));
        $this->setupFcmToken($contractor);

        $this->runReminderJob(self::COMMAND);

        NotificationFacade::assertNotSentTo($contractor, SubscriptionExpiryReminderNotification::class);
        $this->assertNotificationNotCreated($contractor, NotificationType::EXPIRY_REMINDER);
    }

    /**
     * Test: Expiry reminder notification includes correct date.
     */
    public function test_expiry_reminder_notification_includes_correct_date(): void
    {
        NotificationFacade::fake();

        $expiryDate = Carbon::now()->addDays(3)->startOfDay();
        $contractor = $this->createTestContractor();
        $this->giveMembershipExpiring($contractor, $expiryDate);
        $this->setupFcmToken($contractor);

        $this->runReminderJob(self::COMMAND);

        $notification = $this->getLatestNotification($contractor, NotificationType::EXPIRY_REMINDER);
        $this->assertNotNull($notification);
        $this->assertStringContainsString('ينتهي', $notification->body);
        $this->assertStringContainsString($expiryDate->format('Y-m-d'), $notification->body);
        $this->assertSame('/contractor/renewal', $notification->action_url);
    }

    /**
     * Test: Contractor without device token still gets in-app expiry reminder.
     */
    public function test_contractor_without_device_token_still_gets_in_app_expiry_reminder(): void
    {
        NotificationFacade::fake();

        $expiryDate = Carbon::now()->addDays(5);
        $contractor = $this->createTestContractor();
        $this->giveMembershipExpiring($contractor, $expiryDate);
        // بدون fcm_token

        $this->runReminderJob(self::COMMAND);

        // السجلّ داخل التطبيق يُنشأ بغضّ النظر عن وجود رمز الجهاز.
        $this->assertNotificationCreated($contractor, NotificationType::EXPIRY_REMINDER);

        // ولا تُستخدم قناة fcm لمن لا يملك رمز جهاز.
        NotificationFacade::assertSentTo(
            $contractor,
            SubscriptionExpiryReminderNotification::class,
            function (SubscriptionExpiryReminderNotification $notification) use ($contractor) {
                return ! in_array('fcm', $notification->via($contractor), true);
            }
        );
    }
}
