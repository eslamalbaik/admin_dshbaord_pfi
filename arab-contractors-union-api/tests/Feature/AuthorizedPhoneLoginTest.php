<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * جوال المفوض بالتوقيع هو الرقم المعتمد للدخول للتطبيق، والدخول برقم العضوية لسا شغّال.
 */
class AuthorizedPhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $membership, ?string $authorizedPhone, string $password = 'Test@1234', array $extra = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'                    => 'شركة ' . $membership,
            'membership_number'       => $membership,
            'phone'                   => '05900' . random_int(10000, 99999),
            'authorized_person'       => 'المفوض',
            'authorized_person_phone' => $authorizedPhone,
            'password'                => Hash::make($password),
            'status'                  => 'active',
            'is_frozen'               => false,
            'phone_verified_at'       => now(),
        ], $extra));
    }

    public function test_phone_normalization(): void
    {
        foreach (['0599123456', '599123456', '+970 59-912 3456', '00970599123456', '972599123456', '0599 123 456'] as $raw) {
            $this->assertSame('0599123456', PhoneNumber::normalize($raw), $raw);
        }
        $this->assertNull(PhoneNumber::normalize(''));
        $this->assertTrue(PhoneNumber::looksLikeMobile('+970599123456'));
        $this->assertFalse(PhoneNumber::looksLikeMobile('184'));
    }

    public function test_authorized_phone_is_stored_normalized(): void
    {
        $c = $this->contractor('10_g', '+970 599-123-456');

        $this->assertSame('0599123456', $c->fresh()->authorized_person_phone);
    }

    public function test_login_with_authorized_phone_in_any_format(): void
    {
        $c = $this->contractor('11_g', '0599123456');

        foreach (['0599123456', '+970599123456', '00970 599 123 456'] as $typed) {
            $this->postJson('/api/v1/contractor/auth/login', ['identifier' => $typed, 'password' => 'Test@1234'])
                ->assertOk()
                ->assertJsonPath('items.user.membership_number', '11_g');
        }

        // نفس الحقل القديم membership_number بيقبل جوال المفوض كمان
        $this->postJson('/api/v1/contractor/auth/login', ['membership_number' => '0599123456', 'password' => 'Test@1234'])
            ->assertOk();
    }

    public function test_login_with_membership_number_still_works(): void
    {
        $this->contractor('12_g', '0599000111');

        $this->postJson('/api/v1/contractor/auth/login', ['membership_number' => '12', 'password' => 'Test@1234'])->assertOk();
        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '12_g', 'password' => 'Test@1234'])->assertOk();
    }

    public function test_wrong_password_with_authorized_phone(): void
    {
        $this->contractor('13_g', '0599000222');

        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '0599000222', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'invalid_credentials');
    }

    public function test_unknown_authorized_phone(): void
    {
        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '0599999999', 'password' => 'Test@1234'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'authorized_phone_not_found');
    }

    public function test_shared_authorized_phone_picks_company_by_password(): void
    {
        $this->contractor('14_g', '0599000333', 'Alpha@1234');
        $this->contractor('15_g', '0599000333', 'Beta@1234');

        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '0599000333', 'password' => 'Beta@1234'])
            ->assertOk()
            ->assertJsonPath('items.user.membership_number', '15_g');
    }

    public function test_shared_authorized_phone_with_same_password_asks_for_membership_number(): void
    {
        $this->contractor('16_g', '0599000444');
        $this->contractor('17_g', '0599000444');

        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '0599000444', 'password' => 'Test@1234'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'authorized_phone_ambiguous');
    }

    public function test_frozen_contractor_blocked_via_authorized_phone(): void
    {
        $this->contractor('18_g', '0599000555', 'Test@1234', ['is_frozen' => true]);

        $this->postJson('/api/v1/contractor/auth/login', ['identifier' => '0599000555', 'password' => 'Test@1234'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'account_frozen');
    }

    public function test_identifier_required(): void
    {
        $this->postJson('/api/v1/contractor/auth/login', ['password' => 'Test@1234'])->assertStatus(422);
    }
}
