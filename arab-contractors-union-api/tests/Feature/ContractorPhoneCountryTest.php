<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Services\Sms\SmsSenderInterface;
use App\Support\PhoneCountries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * دعم أكثر من دولة لرقم الجوال في التسجيل واستعادة كلمة المرور.
 * contractors.phone = الرقم الوطني، contractors.phone_country_code = رمز الدولة.
 */
class ContractorPhoneCountryTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] */
    private array $sentTo = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsSenderInterface::class, new class($this->sentTo) implements SmsSenderInterface {
            public function __construct(private array &$sentTo) {}

            public function send(string $phone, string $message): bool
            {
                $this->sentTo[] = $phone;

                return true;
            }
        });
    }

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار',
            'membership_number' => '9600_g',
            'phone'             => '1001234567',
            'phone_country_code'=> '20',
            'password'          => Hash::make('OldPass@123'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  normalize
    // ─────────────────────────────────────────────────────────────────────

    public function test_normalize_accepts_local_and_international_forms(): void
    {
        $ps = ['code' => '970', 'national' => '599123456'];

        $this->assertSame($ps, PhoneCountries::normalize(null, '0599123456'));
        $this->assertSame($ps, PhoneCountries::normalize('970', '599123456'));
        $this->assertSame($ps, PhoneCountries::normalize('+970', '٠٥٩٩١٢٣٤٥٦'));
        $this->assertSame($ps, PhoneCountries::normalize(null, '+970 599 123 456'));
        $this->assertSame($ps, PhoneCountries::normalize('20', '00970599123456')); // الرقم الدولي يغلب

        $this->assertSame(['code' => '20', 'national' => '1001234567'], PhoneCountries::normalize('20', '01001234567'));
        $this->assertSame(['code' => '962', 'national' => '791234567'], PhoneCountries::normalize(null, '+962791234567'));

        $this->assertNull(PhoneCountries::normalize('970', '59912'));      // قصير
        $this->assertNull(PhoneCountries::normalize('999', '599123456'));  // دولة غير مدعومة
        $this->assertNull(PhoneCountries::normalize(null, '+999599123456'));
    }

    public function test_countries_endpoint_lists_supported_countries(): void
    {
        $this->getJson('/api/v1/contractor/auth/countries')
            ->assertOk()
            ->assertJsonPath('items.default_code', '970')
            ->assertJsonPath('items.countries.0.code', '970')
            ->assertJsonPath('items.countries.0.dial_code', '+970')
            ->assertJsonPath('items.countries.0.is_default', true)
            ->assertJsonStructure(['items' => ['countries' => [['iso', 'code', 'dial_code', 'name_ar', 'name_en', 'flag', 'min_length', 'max_length', 'example']]]]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  forgot password
    // ─────────────────────────────────────────────────────────────────────

    public function test_forgot_password_with_foreign_number_sends_sms_internationally(): void
    {
        $this->createContractor();

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'country_code' => '20',
            'phone'        => '01001234567',
        ])
            ->assertOk()
            ->assertJsonPath('items.country_code', '20')
            ->assertJsonPath('items.phone_international', '+201001234567');

        $this->assertSame(['+201001234567'], $this->sentTo);
    }

    public function test_forgot_password_accepts_full_international_number_without_country_code(): void
    {
        $this->createContractor();

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['phone' => '+201001234567'])
            ->assertOk();
    }

    public function test_same_digits_in_another_country_do_not_match(): void
    {
        $this->createContractor();

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'country_code' => '966',
            'phone'        => '1001234567',
        ])->assertStatus(422)->assertJsonPath('error', 'invalid_phone'); // 10 أرقام غير صالحة للسعودية

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', [
            'country_code' => '962',
            'phone'        => '100123456',
        ])->assertStatus(404)->assertJsonPath('error', 'contractor_not_found');
    }

    public function test_forgot_password_reset_with_country_code(): void
    {
        $this->createContractor();

        $otp = $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['country_code' => '20', 'phone' => '1001234567'])
            ->assertOk()
            ->json('items.otp_preview');

        $this->postJson('/api/v1/contractor/auth/forgot-password/reset', [
            'country_code'          => '20',
            'phone'                 => '1001234567',
            'otp'                   => (string) $otp,
            'password'              => 'NewPass@123',
            'password_confirmation' => 'NewPass@123',
        ])->assertOk()->assertJsonPath('items.user.country_code', '20');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  register + backward compatibility
    // ─────────────────────────────────────────────────────────────────────

    public function test_register_with_foreign_number(): void
    {
        $this->createContractor(['password' => null, 'phone_verified_at' => null]);

        $otp = $this->postJson('/api/v1/contractor/auth/verify-identity', [
            'country_code'   => '20',
            'phone'          => '01001234567',
            'terms_accepted' => true,
        ])->assertOk()->assertJsonPath('items.phone_international', '+201001234567')->json('items.otp_preview');

        $this->postJson('/api/v1/contractor/auth/verify-otp', [
            'country_code' => '20',
            'phone'        => '1001234567',
            'otp'          => (string) $otp,
        ])->assertOk();

        $this->postJson('/api/v1/contractor/auth/set-password', [
            'country_code'          => '20',
            'phone'                 => '1001234567',
            'password'              => 'NewPass@123',
            'password_confirmation' => 'NewPass@123',
        ])->assertOk();

        $this->assertSame(['+201001234567'], $this->sentTo);
    }

    /** التطبيق القديم يرسل phone فقط — يبقى يعمل للأرقام الفلسطينية، وبصفر البداية أيضاً */
    public function test_old_clients_without_country_code_still_work(): void
    {
        $this->createContractor(['phone' => '592373805', 'phone_country_code' => null]);

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['phone' => '0592373805'])
            ->assertOk()
            ->assertJsonPath('items.country_code', '970');

        $this->assertSame(['+970592373805'], $this->sentTo);
    }

    public function test_970_and_972_are_interchangeable_for_palestinian_numbers(): void
    {
        $this->createContractor(['phone' => '592373805', 'phone_country_code' => '970']);

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['country_code' => '972', 'phone' => '592373805'])
            ->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  رقم الجوال لا يُغيَّر من التطبيق — من لوحة الإدارة فقط
    // ─────────────────────────────────────────────────────────────────────

    private function actingAsContractor(Contractor $contractor): void
    {
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
    }

    public function test_phone_change_otp_endpoints_are_refused(): void
    {
        $this->actingAsContractor($this->createContractor());

        $this->postJson('/api/v1/contractor/auth/profile/phone/request-otp', ['country_code' => '962', 'phone' => '791234567'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'phone_change_not_allowed');

        $this->postJson('/api/v1/contractor/auth/profile/phone/verify-otp', ['otp' => '123456'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'phone_change_not_allowed');

        $this->assertSame([], $this->sentTo);
    }

    /** @dataProvider profileRoutes */
    public function test_profile_save_refuses_a_different_number(string $method, string $uri): void
    {
        $contractor = $this->createContractor(['phone' => '592373805', 'phone_country_code' => '970']);
        $this->actingAsContractor($contractor);

        $this->json($method, $uri, ['phone' => '599999999'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'phone_change_not_allowed');

        // نفس الأرقام بدولة أخرى = رقم مختلف
        $this->json($method, $uri, ['country_code' => '972', 'phone' => '592373805'])
            ->assertStatus(403);

        $this->assertSame('592373805', $contractor->fresh()->phone);
        $this->assertSame('970', $contractor->fresh()->phone_country_code);
    }

    /**
     * التطبيق يعيد إرسال الرقم الحالي مع كل حفظ — يجب ألا يفشل الحفظ بسببه.
     *
     * @dataProvider profileRoutes
     */
    public function test_profile_save_accepts_the_current_number_unchanged(string $method, string $uri): void
    {
        $contractor = $this->createContractor(['phone' => '592373805', 'phone_country_code' => '970', 'address' => 'قديم']);
        $this->actingAsContractor($contractor);

        $this->json($method, $uri, ['phone' => '0592373805', 'address' => 'غزة - الرمال'])
            ->assertOk();

        $this->assertSame('غزة - الرمال', $contractor->fresh()->address);
        $this->assertSame('592373805', $contractor->fresh()->phone);
    }

    public static function profileRoutes(): array
    {
        return [
            'PATCH profile'       => ['PATCH', '/api/v1/contractor/auth/profile'],
            'POST profile/update' => ['POST', '/api/v1/contractor/auth/profile/update'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  رمز التحقق الحقيقي
    // ─────────────────────────────────────────────────────────────────────

    public function test_otp_is_a_random_six_digit_code_sent_in_the_sms(): void
    {
        $messages = [];
        $this->app->instance(SmsSenderInterface::class, new class($messages) implements SmsSenderInterface {
            public function __construct(private array &$messages) {}

            public function send(string $phone, string $message): bool
            {
                $this->messages[] = $message;

                return true;
            }
        });

        $codes = [];
        foreach (['9701_g' => '591111111', '9702_g' => '592222222', '9703_g' => '593333333'] as $membership => $phone) {
            $this->createContractor(['membership_number' => $membership, 'phone' => $phone, 'phone_country_code' => '970']);
            $codes[] = (string) $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['phone' => $phone])
                ->assertOk()
                ->json('items.otp_preview');
        }

        foreach ($codes as $i => $code) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
            $this->assertStringContainsString($code, $messages[$i]);
        }
        $this->assertGreaterThan(1, count(array_unique($codes)), 'الرمز يجب ألا يكون ثابتاً');
    }

    public function test_failed_sms_returns_an_error_and_allows_retry(): void
    {
        $this->app->instance(SmsSenderInterface::class, new class implements SmsSenderInterface {
            public function send(string $phone, string $message): bool
            {
                return false;
            }
        });
        $this->createContractor();

        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['country_code' => '20', 'phone' => '1001234567'])
            ->assertStatus(502)
            ->assertJsonPath('error', 'sms_send_failed');

        // لا cooldown بعد الفشل — إعادة المحاولة فوراً تعطي نفس الخطأ لا otp_cooldown
        $this->postJson('/api/v1/contractor/auth/forgot-password/send-otp', ['country_code' => '20', 'phone' => '1001234567'])
            ->assertStatus(502);
    }
}
