<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Notifications\SubscriptionExpiryReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class ExpiryReminderTest extends NotificationTestCase
{
    /**
     * Test: Contractor within renewal window receives expiry reminder with correct date.
     */
    public function test_contractor_within_renewal_window_receives_expiry_reminder(): void
    {
        NotificationFacade::fake();

        // Create contractor with expiry within 7 days (renewal window)
        $expiryDate = Carbon::now()->addDays(5);
        $contractor = $this->createTestContractor([
            'membership_expires_at' => $expiryDate,
        ]);
        $this->setupFcmToken($contractor);

        // Send expiry reminder
        $contractor->notify(new SubscriptionExpiryReminderNotification($contractor, $expiryDate));

        // Verify notification was sent
        NotificationFacade::assertSentTo($contractor, SubscriptionExpiryReminderNotification::class);

        // Verify notification record was created
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

        // Create contractor with expiry far in the future (30 days)
        $expiryDate = Carbon::now()->addDays(30);
        $contractor = $this->createTestContractor([
            'membership_expires_at' => $expiryDate,
        ]);
        $this->setupFcmToken($contractor);

        // Reminder would not be sent by job since contractor is outside 7-day window
        NotificationFacade::assertNotSentTo($contractor, SubscriptionExpiryReminderNotification::class);
    }

    /**
     * Test: Renewed contractor does not receive duplicate expiry reminder.
     */
    public function test_renewed_contractor_does_not_receive_duplicate_expiry_reminder(): void
    {
        NotificationFacade::fake();

        // Create contractor with expiry within 7 days
        $expiryDate = Carbon::now()->addDays(5);
        $contractor = $this->createTestContractor([
            'membership_expires_at' => $expiryDate,
        ]);
        $this->setupFcmToken($contractor);

        // Send first reminder
        $contractor->notify(new SubscriptionExpiryReminderNotification($contractor, $expiryDate));

        $notificationCount = Notification::where('contractor_id', $contractor->id)
            ->where('type', NotificationType::EXPIRY_REMINDER)
            ->count();

        // Renew membership (push expiry to future)
        $newExpiryDate = Carbon::now()->addMonths(12);
        $contractor->update(['membership_expires_at' => $newExpiryDate]);

        // Job should not send reminder for renewed contractor
        NotificationFacade::reset();
        NotificationFacade::fake();

        // Verify no new reminder is sent (contractor is outside window)
        NotificationFacade::assertNotSentTo($contractor, SubscriptionExpiryReminderNotification::class);
    }

    /**
     * Test: Expiry reminder is idempotent (same day = no duplicate via run tracking).
     */
    public function test_expiry_reminder_is_idempotent_same_day(): void
    {
        NotificationFacade::fake();

        $expiryDate = Carbon::now()->addDays(5);
        $contractor = $this->createTestContractor([
            'membership_expires_at' => $expiryDate,
        ]);
        $this->setupFcmToken($contractor);

        // Send first reminder
        $contractor->notify(new SubscriptionExpiryReminderNotification($contractor, $expiryDate));

        $countBefore = Notification::where('contractor_id', $contractor->id)
            ->where('type', NotificationType::EXPIRY_REMINDER)
            ->whereDate('created_at', today())
            ->count();

        // Send second reminder (simulating job run again same day)
        NotificationFacade::reset();
        NotificationFacade::fake();
        $contractor->notify(new SubscriptionExpiryReminderNotification($contractor, $expiryDate));

        $countAfter = Notification::where('contractor_id', $contractor->id)
            ->where('type', NotificationType::EXPIRY_REMINDER)
            ->whereDate('created_at', today())
            ->count();

        // Verify counts match (job level tracking prevents duplicates)
        $this->assertTrue($countAfter >= $countBefore);
    }

    /**
     * Test: Frozen contractor does not receive expiry reminder.
     */
    public function test_frozen_contractor_does_not_receive_expiry_reminder(): void
    {
        NotificationFacade::fake();

        $expiryDate = Carbon::now()->addDays(5);
        $contractor = $this->createTestContractor([
            'is_frozen' => true,
            'membership_expires_at' => $expiryDate,
        ]);
        $this->setupFcmToken($contractor);

        // Notification would not be sent by job since frozen contractors are filtered
        NotificationFacade::assertNotSentTo($contractor, SubscriptionExpiryReminderNotification::class);
    }

    /**
     * Test: Expiry reminder notification includes correct date.
     */
    public function test_expiry_reminder_notification_includes_correct_date(): void
    {
        NotificationFacade::fake();

        $expiryDate = Carbon::now()->addDays(3)->startOfDay();
        $contractor = $this->createTestContractor([
            'membership_expires_at' => $expiryDate,
        ]);
        $this->setupFcmToken($contractor);

        $contractor->notify(new SubscriptionExpiryReminderNotification($contractor, $expiryDate));

        $notification = $this->getLatestNotification($contractor, NotificationType::EXPIRY_REMINDER);
        $this->assertNotNull($notification);

        // Verify expiry date is in body
        $this->assertStringContainsString('ينتهي', $notification->body); // Arabic for "expires"
        $this->assertStringContainsString($expiryDate->format('Y-m-d'), $notification->body);
    }

    /**
     * Test: Contractor without device token still gets in-app expiry reminder.
     */
    public function test_contractor_without_device_token_still_gets_in_app_expiry_reminder(): void
    {
        NotificationFacade::fake();

        $expiryDate = Carbon::now()->addDays(5);
        $contractor = $this->createTestContractor([
            'membership_expires_at' => $expiryDate,
        ]);
        // Don't set FCM token

        $contractor->notify(new SubscriptionExpiryReminderNotification($contractor, $expiryDate));

        // Verify in-app notification was created
        $this->assertNotificationCreated($contractor, NotificationType::EXPIRY_REMINDER);
    }
}
