<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ContractorBalanceController;
use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * مقاول قديم بننقله للنظام: مربع "عضو قديم" + آخر سنة عضوية بيحددوا حالته من أول يوم.
 */
class ContractorLastMembershipYearTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function create(array $extra = [])
    {
        return $this->post('/api/v1/contractors', array_merge([
            'name'                => 'شركة اختبار للمقاولات',
            'membership_number'   => '961_g',
            'commercial_register' => '563961123',
            'governorate_id'      => 1,
            'city_id'             => 1,
            'district'            => 'الرمال',
            'building'            => 'عمارة 3',
            'floor'               => 'الثاني',
        ], $extra), ['Accept' => 'application/json']);
    }

    public function test_new_member_has_no_membership_and_starts_active(): void
    {
        $this->create(['is_existing_member' => '0', 'last_membership_year' => '2020'])->assertStatus(201);

        $c = Contractor::first();
        $this->assertSame('active', $c->status);
        $this->assertSame(0, $c->memberships()->count());
    }

    public function test_existing_member_with_past_year_is_expired(): void
    {
        $year = now()->year - 1;
        $this->create(['is_existing_member' => '1', 'last_membership_year' => (string) $year])->assertStatus(201);

        $c = Contractor::first();
        $this->assertSame('expired', $c->status);
        $m = $c->memberships()->first();
        $this->assertSame('expired', $m->status);
        $this->assertSame("{$year}-12-31", $m->expires_at->toDateString());
        $this->assertNull($c->activeMembership);
        $this->assertSame('expired', app(ContractorBalanceController::class)->statusFor($c->id));
    }

    public function test_existing_member_with_current_year_is_active(): void
    {
        $year = now()->year;
        $this->create(['is_existing_member' => '1', 'last_membership_year' => (string) $year])->assertStatus(201);

        $c = Contractor::first();
        $this->assertSame('active', $c->status);
        $this->assertSame("{$year}-12-31", $c->activeMembership->expires_at->toDateString());
    }

    public function test_existing_member_requires_a_valid_year(): void
    {
        $this->create(['is_existing_member' => '1'])
            ->assertStatus(422)->assertJsonValidationErrors('last_membership_year');
        $this->create(['is_existing_member' => '1', 'last_membership_year' => (string) (now()->year + 1)])
            ->assertStatus(422)->assertJsonValidationErrors('last_membership_year');
        $this->assertSame(0, Contractor::count());
    }
}
