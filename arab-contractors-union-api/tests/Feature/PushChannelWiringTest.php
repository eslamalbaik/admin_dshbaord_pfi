<?php

namespace Tests\Feature;

use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Models\ContractorNameChangeRequest;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\ProfileUpdateRequest;
use App\Models\SupportTicket;
use App\Models\Tender;
use App\Notifications\AdminBroadcastNotification;
use App\Notifications\CertificateRequestStatusNotification;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\CompleteProfileNotification;
use App\Notifications\MembershipExpiryReminderNotification;
use App\Notifications\MembershipGracePeriodReminderNotification;
use App\Notifications\NameChangeRequestStatusNotification;
use App\Notifications\NewTenderPublishedNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\ProfileUpdateRequestStatusNotification;
use App\Notifications\SupportTicketRepliedNotification;
use App\Services\Push\LogPushSender;
use App\Services\Push\PushSenderInterface;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * يحرس وصول الإشعارات الموجّهة للمقاول إلى قناة FCM. سقوط FcmChannel من via()
 * لا يُحدث أي خطأ — الإشعار ببساطة لا يصل الموبايل بصمت، فالاختبار هنا هو
 * الشيء الوحيد الذي يكشف ذلك.
 */
class PushChannelWiringTest extends TestCase
{
    private function contractor(): Contractor
    {
        return new Contractor([
            'name'              => 'شركة اختبار',
            'membership_number' => '901_g',
            'email'             => 'test@example.com',
            'phone'             => '0599000000',
        ]);
    }

    public static function contractorFacingNotifications(): array
    {
        return [
            'payment confirmed'  => [fn () => new PaymentConfirmedNotification(new Payment(['amount' => 100]))],
            'payment rejected'   => [fn () => new PaymentRejectedNotification(new Payment(['amount' => 100]))],
            'complete profile'   => [fn () => new CompleteProfileNotification()],
            'support replied'    => [fn () => new SupportTicketRepliedNotification(new SupportTicket(['subject' => 'س']))],
            'certificate status' => [fn () => new CertificateRequestStatusNotification(new CertificateRequest(['status' => 'issued']))],
            'name change status' => [fn () => new NameChangeRequestStatusNotification(new ContractorNameChangeRequest(['status' => 'approved']))],
            'expiry reminder'    => [fn () => new MembershipExpiryReminderNotification(new Membership(), 7)],
            'grace reminder'     => [fn () => new MembershipGracePeriodReminderNotification(new Membership(), 'start', now())],
            'profile update status' => [fn () => new ProfileUpdateRequestStatusNotification(new ProfileUpdateRequest(['status' => 'approved']))],
            'admin broadcast'       => [fn () => new AdminBroadcastNotification('عنوان', 'نص')],
            'new tender published'  => [fn () => new NewTenderPublishedNotification(new Tender(['title' => 'عطاء اختبار']))],
        ];
    }

    #[DataProvider('contractorFacingNotifications')]
    public function test_contractor_notification_reaches_the_fcm_channel(callable $make): void
    {
        $channels = $make()->via($this->contractor());

        $this->assertContains(FcmChannel::class, $channels);
        $this->assertContains('database', $channels, 'سجل الإشعارات داخل التطبيق يجب أن يبقى إلى جانب الـ push');
    }

    public function test_fcm_channel_skips_contractor_without_token(): void
    {
        $sent = false;
        $this->app->bind(PushSenderInterface::class, function () use (&$sent) {
            return new class($sent) implements PushSenderInterface {
                public function __construct(private &$flag)
                {
                }

                public function send(string $fcmToken, string $title, string $body, array $data = []): bool
                {
                    $this->flag = true;

                    return true;
                }
            };
        });

        $contractor = $this->contractor();  // لا fcm_token
        $this->app->make(FcmChannel::class)->send(
            $contractor,
            new PaymentConfirmedNotification(new Payment(['amount' => 100])),
        );

        $this->assertFalse($sent, 'لا يجوز محاولة الإرسال لمقاول بلا fcm_token');
    }

    public function test_log_sender_reports_success_without_delivering(): void
    {
        // توثيق للسلوك المُربِك: LogPushSender يرجّع true دائماً. أي كود يعتمد على
        // قيمة الإرجاع ليقرر "وصل الإشعار" سيكون مخطئاً في بيئة بلا Firebase.
        Log::shouldReceive('channel')->with('push')->andReturnSelf();
        Log::shouldReceive('info')->once();

        $this->assertTrue((new LogPushSender())->send('fake-token', 'عنوان', 'نص'));
    }
}
