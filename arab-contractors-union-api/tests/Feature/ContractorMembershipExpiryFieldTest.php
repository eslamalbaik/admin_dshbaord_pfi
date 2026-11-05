<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * حقل "تاريخ انتهاء العضوية" بفورم المقاول (2026-10-12): بيتخزّن على memberships.expires_at،
 * وهو وحده اللي بيحدد "فعّالة/منتهية" بالداشبورد والتطبيق.
 */
class ContractorMembershipExpiryFieldTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $status = 'active'): Contractor
    {
        return Contractor::create([
            'name'                => 'شركة مقاولات فحص',
            'membership_number'   => '930_g',
            'commercial_register' => '123456789',
            'status'              => $status,
            'is_frozen'           => false,
        ]);
    }

    private function save(Contractor $c, ?string $expiresAt)
    {
        return $this->putJson("/api/v1/contractors/{$c->id}", [
            'name'                  => $c->name,
            'membership_number'     => $c->membership_number,
            'commercial_register'   => $c->commercial_register,
            'membership_expires_at' => $expiresAt,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    public function test_setting_a_future_date_creates_a_membership_and_shows_active(): void
    {
        $c = $this->contractor('pending');
        $date = now()->addYear()->toDateString();

        $this->save($c, $date)->assertOk();

        $membership = Membership::where('contractor_id', $c->id)->sole();
        $this->assertSame('active', $membership->status);
        $this->assertSame($date, $membership->expires_at->toDateString());
        $this->assertSame('active', $c->fresh()->status, 'المعلّق برجع نشط لما ينحطله تاريخ لسا ما فات');

        $this->getJson("/api/v1/contractors/{$c->id}")
            ->assertJsonPath('items.membership_expires_at', $date)
            ->assertJsonPath('items.membership_status', 'active');
        $this->getJson('/api/v1/contractors?search=930_g')
            ->assertJsonPath('items.0.membership_status', 'active')
            ->assertJsonPath('items.0.membership_expires_at', $date);
    }

    public function test_changing_the_date_updates_the_existing_membership(): void
    {
        $c = $this->contractor();
        Membership::create([
            'contractor_id' => $c->id, 'type' => 'new', 'status' => 'active', 'expires_at' => now()->addMonth(),
        ]);

        $this->save($c, now()->subDay()->toDateString())->assertOk();

        $this->assertSame(1, Membership::where('contractor_id', $c->id)->count());
        $this->getJson('/api/v1/dashboard/balances?search=930_g')->assertJsonPath('items.0.status', 'expired');
    }

    public function test_an_empty_field_leaves_the_date_unchanged(): void
    {
        $c = $this->contractor();
        $date = now()->addMonth()->toDateString();
        Membership::create(['contractor_id' => $c->id, 'type' => 'new', 'status' => 'active', 'expires_at' => $date]);

        $this->save($c, null)->assertOk();

        $this->assertSame($date, Membership::where('contractor_id', $c->id)->sole()->expires_at->toDateString());
    }

    public function test_rejects_an_invalid_date(): void
    {
        $this->save($this->contractor(), 'not-a-date')->assertUnprocessable()
            ->assertJsonValidationErrors('membership_expires_at');
    }

    /** بلاغ 930_g: الحالة الإدارية active بدون أي عضوية — التطبيق كان يقول "نشط" والداشبورد "منتهي" */
    public function test_app_home_badge_follows_the_expiry_date(): void
    {
        $c = $this->contractor();

        Sanctum::actingAs($c, ['*']);
        $this->getJson('/api/v1/contractor/home')->assertOk()
            ->assertJsonPath('items.membership.badge', 'expired')
            ->assertJsonPath('items.membership.expires_at', null);

        $date = now()->addMonth()->toDateString();
        Membership::create(['contractor_id' => $c->id, 'type' => 'new', 'status' => 'active', 'expires_at' => $date]);

        $this->getJson('/api/v1/contractor/home')->assertOk()
            ->assertJsonPath('items.membership.badge', 'active')
            ->assertJsonPath('items.membership.expires_at', $date);
    }
}
