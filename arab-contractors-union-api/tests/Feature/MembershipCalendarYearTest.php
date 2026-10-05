<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Membership;
use App\Services\MembershipRenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * كل العضويات تنتهي 31/12 من السنة المغطّاة، مهما كان تاريخ الدفع أو الموافقة.
 */
class MembershipCalendarYearTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contractor(): Contractor
    {
        return Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '940_g',
            'status'            => 'pending',
            'is_frozen'         => false,
        ]);
    }

    private function renew(Contractor $contractor, string $type = 'new'): Membership
    {
        $membership = $contractor->memberships()->create(['type' => $type, 'status' => 'pending']);

        return app(MembershipRenewalService::class)->applyRenewal($membership);
    }

    public function test_new_member_paying_mid_year_expires_on_dec_31_of_that_year(): void
    {
        Carbon::setTestNow('2026-10-05 10:00:00');

        $membership = $this->renew($this->contractor());

        $this->assertSame('2026-10-05', $membership->starts_at->toDateString());
        $this->assertSame('2026-12-31', $membership->expires_at->toDateString());
        $this->assertSame('active', $membership->contractor->fresh()->status);
    }

    public function test_paying_on_dec_30_still_expires_dec_31_same_year(): void
    {
        Carbon::setTestNow('2026-12-30 09:00:00');

        $membership = $this->renew($this->contractor());

        $this->assertSame('2026-12-31', $membership->expires_at->toDateString());
    }

    public function test_renewal_after_expired_membership_covers_current_year_only(): void
    {
        Carbon::setTestNow('2026-03-15 09:00:00');
        $contractor = $this->contractor();
        $contractor->memberships()->create([
            'type' => 'new', 'status' => 'active',
            'starts_at' => '2025-01-01', 'expires_at' => '2025-12-31',
        ]);

        $membership = $this->renew($contractor, 'renewal');

        $this->assertSame('2026-12-31', $membership->expires_at->toDateString());
    }

    public function test_prepaying_while_current_year_is_covered_extends_to_next_dec_31(): void
    {
        Carbon::setTestNow('2026-11-20 09:00:00');
        $contractor = $this->contractor();
        $contractor->memberships()->create([
            'type' => 'new', 'status' => 'active',
            'starts_at' => '2026-01-01', 'expires_at' => '2026-12-31',
        ]);

        $membership = $this->renew($contractor, 'renewal');

        $this->assertSame('2027-01-01', $membership->starts_at->toDateString());
        $this->assertSame('2027-12-31', $membership->expires_at->toDateString());
    }
}
