<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Notifications\SubscriptionPaymentReminderNotification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * تذكير سداد الاشتراك — يمرّ عبر أمر memberships:send-grace-period-reminders،
 * وهو الجهة التي تنشئ سجلّ app_notifications (notify() وحده لا ينشئه).
 * الاستحقاق يُقرأ من contractor_dues.status (unpaid/partially_paid = مستحقة).
 */
class PaymentReminderTest extends NotificationTestCase
{
    private const COMMAND = 'memberships:send-grace-period-reminders';

    /**
     * Test: Contractor with outstanding payment receives reminder.
     */
    public function test_contractor_with_outstanding_payment_receives_reminder(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);
        $this->giveDue($contractor, 'unpaid');

        $this->runReminderJob(self::COMMAND);

        NotificationFacade::assertSentTo($contractor, SubscriptionPaymentReminderNotification::class);
        $this->assertNotificationCreated($contractor, NotificationType::PAYMENT_REMINDER);
    }

    /**
     * Test: Contractor with a partially paid due is still reminded.
     */
    public function test_partially_paid_contractor_receives_reminder(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);
        $this->giveDue($contractor, 'partially_paid');

        $this->runReminderJob(self::COMMAND);

        $this->assertNotificationCreated($contractor, NotificationType::PAYMENT_REMINDER);
    }

    /**
     * Test: Fully paid contractor does not receive reminder.
     */
    public function test_fully_paid_contractor_does_not_receive_reminder(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);
        $this->giveDue($contractor, 'paid');

        $this->runReminderJob(self::COMMAND);

        NotificationFacade::assertNotSentTo($contractor, SubscriptionPaymentReminderNotification::class);
        $this->assertNotificationNotCreated($contractor, NotificationType::PAYMENT_REMINDER);
    }

    /**
     * Test: Payment reminder is idempotent (same run date = no duplicate).
     */
    public function test_payment_reminder_is_idempotent_same_day(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);
        $this->giveDue($contractor, 'unpaid');

        $this->runReminderJob(self::COMMAND);
        $countAfterFirstRun = $this->countNotificationsToday($contractor, NotificationType::PAYMENT_REMINDER);

        // إعادة التشغيل بنفس التاريخ تُتخطّى عبر NotificationRun الموجود.
        $this->runReminderJob(self::COMMAND);
        $countAfterSecondRun = $this->countNotificationsToday($contractor, NotificationType::PAYMENT_REMINDER);

        $this->assertSame(1, $countAfterFirstRun);
        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);
    }

    /**
     * Test: Frozen contractor does not receive payment reminder.
     */
    public function test_frozen_contractor_does_not_receive_payment_reminder(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor(['is_frozen' => true]);
        $this->setupFcmToken($contractor);
        $this->giveDue($contractor, 'unpaid');

        $this->runReminderJob(self::COMMAND);

        NotificationFacade::assertNotSentTo($contractor, SubscriptionPaymentReminderNotification::class);
        $this->assertNotificationNotCreated($contractor, NotificationType::PAYMENT_REMINDER);
    }

    /**
     * Test: Contractor without device token still gets in-app reminder.
     */
    public function test_contractor_without_device_token_still_gets_in_app_payment_reminder(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        // بدون fcm_token
        $this->giveDue($contractor, 'unpaid');

        $this->runReminderJob(self::COMMAND);

        $this->assertNotificationCreated($contractor, NotificationType::PAYMENT_REMINDER);

        NotificationFacade::assertSentTo(
            $contractor,
            SubscriptionPaymentReminderNotification::class,
            function (SubscriptionPaymentReminderNotification $notification) use ($contractor) {
                return ! in_array('fcm', $notification->via($contractor), true);
            }
        );
    }

    /**
     * Test: Payment reminder notification links to payment page.
     */
    public function test_payment_reminder_notification_links_to_payment_page(): void
    {
        NotificationFacade::fake();

        $contractor = $this->createTestContractor();
        $this->setupFcmToken($contractor);
        $this->giveDue($contractor, 'unpaid');

        $this->runReminderJob(self::COMMAND);

        $notification = $this->getLatestNotification($contractor, NotificationType::PAYMENT_REMINDER);
        $this->assertNotNull($notification);
        $this->assertSame('/contractor/payment', $notification->action_url);
    }
}
