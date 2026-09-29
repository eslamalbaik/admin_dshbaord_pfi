<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Rules\MembershipNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * كل أرقام العضوية بصيغة {n}_g — بما فيها القديمة (1-927). المقاول القديم الذي
 * يكتب رقمه بدون اللاحقة يُقبل منه ويُكمَل تلقائياً.
 */
class MembershipNumberGSuffixTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_accepts_only_g_suffixed_numbers(): void
    {
        $this->assertTrue(MembershipNumber::isValid('184_g'));
        $this->assertTrue(MembershipNumber::isValid('932_g'));
        $this->assertFalse(MembershipNumber::isValid('184'));
        $this->assertFalse(MembershipNumber::isValid('0_g'));
        $this->assertSame('184_g', MembershipNumber::normalize(' 184 '));
        $this->assertSame('184_g', MembershipNumber::normalize('184_g'));
    }

    public function test_legacy_contractor_can_login_typing_number_without_suffix(): void
    {
        Contractor::create([
            'name'              => 'شركة قديمة',
            'membership_number' => '184_g',
            'phone'             => '0590001122',
            'password'          => Hash::make('Test@1234'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/contractor/auth/login', [
            'membership_number' => '184',
            'password'          => 'Test@1234',
        ]);

        $this->assertNotSame('no_membership', $response->json('error_key'));
        $this->assertNotSame(404, $response->status());
    }
}
