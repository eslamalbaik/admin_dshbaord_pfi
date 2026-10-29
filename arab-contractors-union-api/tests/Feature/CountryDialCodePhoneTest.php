<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\CountryDialCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * جوال وواتساب المفوض بيتفحصوا على مقدمات الدول اللي بيديرها الأدمن، وجوال الحساب انشال من
 * فورم اللوحة فالتسجيل بالتطبيق بيقبل جوال المفوض لمقاول ما إله جوال حساب.
 */
class CountryDialCodePhoneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    public function test_phone_validation_against_dial_codes(): void
    {
        foreach (['+970599123456', '00970599123456', '0599123456', '599123456', '970599123456', '+9700599123456', '+962791234567', '962791234567', '+201012345678'] as $ok) {
            $this->assertTrue(CountryDialCode::isValidPhone($ok), $ok);
        }
        foreach (['+970499123456', '+97059912345', '059912345', '+962591234567', '+999123456789', '12345', 'abc599123456'] as $bad) {
            $this->assertFalse(CountryDialCode::isValidPhone($bad), $bad);
        }
    }

    public function test_hidden_dial_code_is_rejected(): void
    {
        CountryDialCode::where('dial_code', '962')->update(['is_active' => false]);

        $this->assertFalse(CountryDialCode::isValidPhone('+962791234567'));
    }

    public function test_contact_update_validates_authorized_phone(): void
    {
        $this->admin();
        $c = Contractor::create(['name' => 'شركة', 'membership_number' => '70_g', 'phone' => '0590001122', 'status' => 'active']);

        $this->patchJson("/api/v1/contractors/{$c->id}/contact", ['authorized_person_phone' => '+970 499 123 456'])
            ->assertStatus(422);

        $this->patchJson("/api/v1/contractors/{$c->id}/contact", [
            'authorized_person_phone'    => '+970599123456',
            'authorized_person_whatsapp' => '+962791234567',
        ])->assertOk();

        $c->refresh();
        $this->assertSame('0599123456', $c->authorized_person_phone);
        $this->assertSame('+962791234567', $c->authorized_person_whatsapp);
        $this->assertSame('0590001122', $c->phone, 'جوال الحساب القديم ما بينمسح');
    }

    public function test_admin_manages_dial_codes(): void
    {
        $this->admin();

        $id = $this->postJson('/api/v1/dashboard/country-codes', [
            'name' => 'العراق', 'iso_code' => 'iq', 'dial_code' => '+964', 'min_digits' => 10, 'max_digits' => 10, 'starts_with' => '7',
        ])->assertCreated()->assertJsonPath('items.dial_code', '964')->json('items.id');

        $this->assertTrue(CountryDialCode::isValidPhone('+9647701234567'));

        $this->postJson("/api/v1/dashboard/country-codes/{$id}", ['_method' => 'PATCH', 'is_default' => 1])->assertOk();
        $this->assertSame(1, CountryDialCode::where('is_default', true)->count());

        $this->deleteJson("/api/v1/dashboard/country-codes/{$id}")->assertStatus(422);

        $this->getJson('/api/v1/app/country-codes')->assertOk()->assertJsonPath('items.0.dial_code', '964');
    }

    public function test_registration_falls_back_to_authorized_phone(): void
    {
        $c = Contractor::create([
            'name' => 'شركة', 'membership_number' => '71_g', 'status' => 'active',
            'authorized_person_phone' => '0599111222',
        ]);

        $this->postJson('/api/v1/contractor/auth/verify-identity', ['phone' => '+970599111222', 'terms_accepted' => true])
            ->assertOk()
            ->assertJsonPath('items.phone', '0599111222');

        // نفس المفوض لشركتين بدون جوال حساب ← ما بنخمّن أي شركة
        Contractor::create(['name' => 'شركة 2', 'membership_number' => '72_g', 'status' => 'active', 'authorized_person_phone' => '0599111222']);
        $this->assertNull(Contractor::findByRegistrationPhone('0599111222'));
    }
}
