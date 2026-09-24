<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Models\Announcement;
use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\NotificationRun;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * قاعدة مشتركة لاختبارات نظام إشعارات تطبيق المقاول (جدول app_notifications).
 *
 * ملاحظتان تحكمان شكل هذه الاختبارات:
 *  1. سجلّ App\Models\Notification لا ينشئه notify() — ينشئه المُستدعي
 *     (SendRenewalReminders / SendGracePeriodReminders / SendAnnouncementNotifications).
 *     لذلك نشغّل الأمر أو الحدث الحقيقي، لا $contractor->notify() مباشرة.
 *  2. تاريخ انتهاء الاشتراك مصدره memberships.expires_at — لا عمود له على contractors.
 */
class NotificationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * مقاول فعّال غير مجمّد.
     */
    protected function createTestContractor(array $attributes = []): Contractor
    {
        return Contractor::factory()->create(array_merge([
            'is_frozen' => false,
            'status'    => 'active',
        ], $attributes));
    }

    /**
     * يمنح المقاول عضوية بتاريخ انتهاء محدّد — المصدر الوحيد لنافذة التجديد.
     */
    protected function giveMembershipExpiring(
        Contractor $contractor,
        Carbon $expiresAt,
        string $status = 'active'
    ): Membership {
        return Membership::factory()->create([
            'contractor_id' => $contractor->id,
            'status'        => $status,
            'starts_at'     => $expiresAt->copy()->subYear(),
            'expires_at'    => $expiresAt,
        ]);
    }

    /**
     * ذمّة على المقاول — status هو ما تقرأه NotificationHelper
     * (unpaid/partially_paid = مستحقة، paid = مسدَّدة).
     */
    protected function giveDue(Contractor $contractor, string $status = 'unpaid'): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $contractor->id,
            'year'          => (int) now()->year,
            'description'   => 'رسوم اشتراك اختبارية',
            'amount_jod'    => 1000,
            'paid_jod'      => $status === 'paid' ? 1000 : 500,
            'status'        => $status,
        ]);
    }

    /**
     * Set up a device token for a contractor to receive FCM notifications.
     */
    protected function setupFcmToken(Contractor $contractor, ?string $token = null): Contractor
    {
        $token = $token ?? 'test-fcm-token-' . $contractor->id;
        $contractor->update(['fcm_token' => $token]);

        return $contractor->refresh();
    }

    /**
     * إعلان منشور فعلاً (is_published + published_at في الماضي) — لا يوجد عمود status.
     */
    protected function publishAnnouncement(array $attributes = []): Announcement
    {
        return Announcement::factory()->published()->create($attributes);
    }

    /**
     * Run a reminder command and return its exit code.
     */
    protected function runReminderJob(string $jobCommand, array $parameters = []): int
    {
        return Artisan::call($jobCommand, $parameters);
    }

    /**
     * Assert that a notification was created for a contractor.
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
     * Assert that a notification was marked as read.
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
     */
    protected function getLatestNotification(Contractor $contractor, NotificationType $type): ?Notification
    {
        return Notification::where('contractor_id', $contractor->id)
            ->where('type', $type)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * عدد إشعارات نوع معيّن لمقاول اليوم.
     */
    protected function countNotificationsToday(Contractor $contractor, NotificationType $type): int
    {
        return Notification::where('contractor_id', $contractor->id)
            ->where('type', $type)
            ->whereDate('created_at', today())
            ->count();
    }

    /**
     * Assert that a notification run was completed.
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
