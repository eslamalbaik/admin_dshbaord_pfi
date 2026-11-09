<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Services\Sms\SmsSenderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** رمز التحقق الحقيقي: عشوائي، يصل نصه بالرسالة، وفشل الإرسال لا يُخفى عن المستخدم. */
class OtpDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function createContractor(string $membership, string $phone): Contractor
    {
        return Contractor::create([
            'name'              => 'شركة اختبار',
            'membership_number' => $membership,
            'phone'             => $phone,
            'password'          => Hash::make('OldPass@123'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ]);
    }

    public function test_otp_is_a_random_six_digit_code_sent_in_the_sms(): void
    {
        $sent = [];
        $this->app->instance(SmsSenderInterface::class, new class($sent) implements SmsSenderInterface {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message): bool
            {
                $this->sent[] = $message;

                return true;
            }
        });

        $codes = [];
        foreach (['9701_g' => '0591111111', '9702_g' => '0592222222', '9703_g' => '0593333333'] as $membership => $phone) {
            $this->createContractor($membership, $phone);
            $codes[] = (string) $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['phone' => $phone])
                ->assertOk()
                ->json('items.otp_preview');
        }

        foreach ($codes as $i => $code) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            $this->assertStringContainsString($code, $sent[$i]);
        }
        $this->assertGreaterThan(1, count(array_unique($codes)), 'الرمز يجب ألا يكون ثابتاً');
    }

    public function test_fixed_otp_code_is_used_outside_production(): void
    {
        config(['services.sms.fixed_otp' => '123456']);
        $this->createContractor('9705_g', '0595555555');

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['phone' => '0595555555'])
            ->assertOk()
            ->assertJsonPath('items.otp_preview', 123456);
    }

    public function test_fixed_otp_code_is_ignored_in_production(): void
    {
        config(['services.sms.fixed_otp' => '123456']);
        $this->app['env'] = 'production';

        $otp = $this->app->make(\App\Services\OtpService::class)
            ->sendOtp('test', 1, '+970591111111', 'رمز التحقق: {otp}');

        $this->assertNotSame(123456, $otp['otp']);
    }

    public function test_failed_sms_returns_an_error_and_allows_retry(): void
    {
        $this->app->instance(SmsSenderInterface::class, new class implements SmsSenderInterface {
            public function send(string $phone, string $message): bool
            {
                return false;
            }
        });
        $this->createContractor('9704_g', '0594444444');

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['phone' => '0594444444'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'sms_send_failed');

        // لا cooldown بعد الفشل — إعادة المحاولة فوراً تعطي نفس الخطأ لا otp_cooldown
        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['phone' => '0594444444'])
            ->assertStatus(502);
    }
}
