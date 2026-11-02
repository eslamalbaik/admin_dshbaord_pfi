<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\CountryDialCode;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * جوال المفوض من أي دولة مفعّلة بالإعدادات (مش بس فلسطين): بينحفظ بصيغة موحّدة وبيدخل فيه للتطبيق.
 */
class MultiCountryAuthorizedPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $membership, ?string $authorizedPhone, string $password = 'Test@1234'): Contractor
    {
        return Contractor::create([
            'name'                    => 'شركة ' . $membership,
            'membership_number'       => $membership,
            'phone'                   => '05900' . random_int(10000, 99999),
            'authorized_person'       => 'المفوض',
            'authorized_person_phone' => $authorizedPhone,
            'password'                => Hash::make($password),
            'status'                  => 'active',
            'is_frozen'               => false,
            'phone_verified_at'       => now(),
        ]);
    }

    public function test_international_numbers_normalize_to_one_format(): void
    {
        foreach (['+962791234567', '+962 79 123 4567', '00962791234567', '00962 0791234567', '962791234567', '+962 (0)79-123-4567'] as $raw) {
            $this->assertSame('962791234567', PhoneNumber::normalize($raw), $raw);
        }
        $this->assertSame('201012345678', PhoneNumber::normalize('+20 10 1234 5678'));
        $this->assertSame('966512345678', PhoneNumber::normalize('00966 0512345678'));

        // فلسطين ما تغيّرت
        foreach (['0599123456', '599123456', '+970599123456', '+9700599123456', '972599123456'] as $raw) {
            $this->assertSame('0599123456', PhoneNumber::normalize($raw), $raw);
        }
    }

    public function test_international_display_format(): void
    {
        $this->assertSame('+970599123456', PhoneNumber::international('0599123456'));
        $this->assertSame('+962791234567', PhoneNumber::international('962791234567'));
        $this->assertNull(PhoneNumber::international(null));
    }

    public function test_looks_like_mobile(): void
    {
        foreach (['+962791234567', '00962791234567', '962791234567', '+201012345678', '0599123456'] as $phone) {
            $this->assertTrue(PhoneNumber::looksLikeMobile($phone), $phone);
        }
        foreach (['184', '184_g', '9565', '9565_g'] as $membership) {
            $this->assertFalse(PhoneNumber::looksLikeMobile($membership), $membership);
        }
    }

    public function test_login_with_jordanian_authorized_phone_in_any_format(): void
    {
        $c = $this->contractor('21_g', '+962 79 123 4567');
        $this->assertSame('962791234567', $c->fresh()->authorized_person_phone);

        foreach (['+962791234567', '00962 79 123 4567', '962791234567', '+962 0791234567'] as $typed) {
            $this->postJson('/api/v1/contractor/auth/login', ['identifier' => $typed, 'password' => 'Test@1234'])
                ->assertOk()
                ->assertJsonPath('items.user.membership_number', '21_g')
                ->assertJsonPath('items.user.authorized_person_phone', '962791234567')
                ->assertJsonPath('items.user.authorized_person_phone_international', '+962791234567');
        }
    }

    public function test_palestinian_login_returns_international_field(): void
    {
        $this->contractor('22_g', '0599123456');

        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '+970599123456', 'password' => 'Test@1234'])
            ->assertOk()
            ->assertJsonPath('items.user.authorized_person_phone', '0599123456')
            ->assertJsonPath('items.user.authorized_person_phone_international', '+970599123456');
    }

    public function test_unknown_international_phone_is_reported_as_phone(): void
    {
        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '+962799999999', 'password' => 'Test@1234'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'authorized_phone_not_found');
    }

    public function test_disabled_country_still_logs_in_if_number_was_saved(): void
    {
        $this->contractor('23_g', '+962791234567');
        CountryDialCode::where('dial_code', '962')->update(['is_active' => false]);

        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '+962791234567', 'password' => 'Test@1234'])
            ->assertOk()
            ->assertJsonPath('items.user.membership_number', '23_g');
    }

    public function test_registration_otp_lookup_accepts_international_signatory_phone(): void
    {
        $c = Contractor::create([
            'name' => 'شركة 24_g', 'membership_number' => '24_g', 'authorized_person' => 'المفوض',
            'authorized_person_phone' => '+962791234567', 'status' => 'active',
        ]);

        $this->assertSame($c->id, Contractor::findByRegistrationPhone('00962 0791234567')?->id);
    }
}
