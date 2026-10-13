<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * تاريخ انتهاء العضوية يُرجَع للتطبيق حتى لما تكون العضوية منتهية
 * (آخر عضوية بحسب expires_at)، وnull فقط لما ما في أي عضوية.
 */
class ExpiredMembershipExpiryDateTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(): Contractor
    {
        $contractor = Contractor::create([
            'name'              => 'شركة اختبار',
            'membership_number' => '950_g',
            'status'            => 'expired',
            'is_frozen'         => false,
        ]);
        Sanctum::actingAs($contractor, ['*']);
        $this->actingAs($contractor, 'contractor');

        return $contractor;
    }

    private function membership(Contractor $c, int $year, string $status = 'expired'): Membership
    {
        return Membership::create([
            'contractor_id' => $c->id,
            'type'          => 'renewal',
            'status'        => $status,
            'starts_at'     => "$year-01-01",
            'expires_at'    => "$year-12-31",
            'amount'        => 0,
        ]);
    }

    public function test_expired_membership_returns_last_expiry_date_everywhere(): void
    {
        $c = $this->contractor();
        $this->membership($c, 2024);
        $this->membership($c, 2025);
        // طلب مرفوض/معلّق ما بيحسب
        Membership::create(['contractor_id' => $c->id, 'type' => 'renewal', 'status' => 'rejected',
            'starts_at' => '2026-01-01', 'expires_at' => '2026-12-31', 'amount' => 0]);

        $home = $this->getJson('/api/v1/contractor/home')->assertOk();
        $this->assertSame('2025-12-31', $home->json('items.membership.expires_at'));
        $this->assertSame('expired', $home->json('items.membership.badge'));

        $profile = $this->getJson('/api/v1/contractor/auth/profile')->assertOk();
        $this->assertSame('2025-12-31', $profile->json('items.membership.expires_at'));
        $this->assertSame('expired', $profile->json('items.membership.status'));
        $this->assertTrue($profile->json('items.membership.is_expired'));

        $dashboard = $this->getJson('/api/v1/contractor/dashboard')->assertOk();
        $this->assertSame('2025-12-31', $dashboard->json('items.membership.expires_at'));

        $balance = $this->getJson('/api/v1/contractor/balance')->assertOk();
        $this->assertSame('2025-12-31', $balance->json('items.membership_expires_at'));
    }

    public function test_active_membership_still_wins(): void
    {
        $c = $this->contractor();
        $this->membership($c, 2025);
        $year = (int) now()->year;
        $this->membership($c, $year, 'active');

        $this->getJson('/api/v1/contractor/auth/profile')->assertOk()
            ->assertJsonPath('items.membership.expires_at', "$year-12-31")
            ->assertJsonPath('items.membership.status', 'active');
        $this->getJson('/api/v1/contractor/balance')->assertOk()
            ->assertJsonPath('items.membership_expires_at', "$year-12-31");
    }

    public function test_no_membership_returns_null(): void
    {
        $this->contractor();

        $this->getJson('/api/v1/contractor/home')->assertOk()
            ->assertJsonPath('items.membership.expires_at', null);
        $this->getJson('/api/v1/contractor/auth/profile')->assertOk()
            ->assertJsonPath('items.membership', null);
        $this->getJson('/api/v1/contractor/balance')->assertOk()
            ->assertJsonPath('items.membership_expires_at', null);
    }
}
