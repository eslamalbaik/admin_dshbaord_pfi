<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * الدخول بجوال الحساب (contractors.phone) مش بس بجوال المفوض — نفس اللي بتدوّر فيه استعادة
 * كلمة السر، وإلا المقاول بيسترجع كلمة السر برقم ما بيقدر يدخل فيه.
 */
class AccountPhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $membership, ?string $phone, ?string $authorizedPhone = null, string $password = 'Test@1234'): Contractor
    {
        return Contractor::create([
            'name'                    => 'شركة ' . $membership,
            'membership_number'       => $membership,
            'phone'                   => $phone,
            'authorized_person'       => 'المفوض',
            'authorized_person_phone' => $authorizedPhone,
            'password'                => Hash::make($password),
            'status'                  => 'active',
            'is_frozen'               => false,
            'phone_verified_at'       => now(),
        ]);
    }

    private function login(string $identifier, string $password = 'Test@1234')
    {
        return $this->postJson('/api/v1/contractor/auth/login', ['membership_number' => $identifier, 'password' => $password]);
    }

    /** الحالة المُبلَّغ عنها: جوال حساب قديم الصيغة (بدون صفر) وجوال مفوض فاضي */
    public function test_legacy_account_phone_without_leading_zero_can_log_in_in_any_format(): void
    {
        $c = $this->contractor('932_g', '592860416');

        // 5 صيغ بس — تسجيل الدخول محدود بـ throttle:auth (5 بالدقيقة)
        foreach (['0592860416', '592860416', '+970592860416', '00970 592 860 416', '932'] as $typed) {
            $this->login($typed)->assertOk();
            $this->assertNotNull($c->fresh()->last_login_at, $typed);
        }
    }

    public function test_account_phone_with_leading_zero_can_log_in(): void
    {
        $this->contractor('933_g', '0592860417');

        $this->login('592860417')->assertOk();
        $this->login('0592860417')->assertOk();
    }

    public function test_wrong_password_with_account_phone_is_rejected(): void
    {
        $this->contractor('934_g', '592860418');

        $this->login('0592860418', 'Wrong@1234')
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_credentials');
    }

    /** نفس الرقم جوال حساب لشركة وجوال مفوض لشركة ثانية — كلمة السر بتحدد الشركة */
    public function test_password_picks_the_company_when_the_number_is_shared(): void
    {
        $a = $this->contractor('935_g', '592860419', null, 'First@1234');
        $b = $this->contractor('936_g', '0591111111', '0592860419', 'Second@1234');

        $this->login('0592860419', 'First@1234')->assertOk();
        $this->assertNotNull($a->fresh()->last_login_at);

        $this->login('0592860419', 'Second@1234')->assertOk();
        $this->assertNotNull($b->fresh()->last_login_at);
    }

    public function test_unknown_phone_is_not_found(): void
    {
        $this->contractor('937_g', '592860420');

        $this->login('0599999999')
            ->assertStatus(404)
            ->assertJsonPath('error', 'authorized_phone_not_found');
    }
}
