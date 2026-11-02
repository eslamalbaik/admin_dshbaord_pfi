<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * شاشتا الأرصدة (dashboard/balances) والذمم (dashboard/dues/by-contractor) بترتّب افتراضياً حسب
 * آخر نشاط مالي: الشركة اللي انضافلها ذمة أو رسوم أو رصيد بتطلع أول صف، حتى بعد إعادة تحميل الصفحة.
 */
class FinancialListsRecentActivityOrderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Contractor, 1: Contractor, 2: Contractor} ثلاث شركات عليها ذمم قديمة، الأولى أكبر مديونية */
    private function seedContractors(): array
    {
        $this->travelTo(now()->subDays(10));

        $rows = [];
        foreach ([['960_g', 900], ['961_g', 500], ['962_g', 100]] as [$membership, $amount]) {
            $c = Contractor::create([
                'name' => "شركة {$membership}", 'membership_number' => $membership,
                'status' => 'active', 'is_frozen' => false,
            ]);
            ContractorDue::create([
                'contractor_id' => $c->id, 'description' => 'رسوم قديمة', 'year' => 2025,
                'amount_jod' => $amount, 'paid_jod' => 0, 'status' => 'unpaid',
            ]);
            $rows[] = $c;
        }

        $this->travelBack();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        return $rows;
    }

    private function firstBalanceId(string $query = ''): int
    {
        return $this->getJson("/api/v1/dashboard/balances{$query}")->assertOk()->json('items.0.contractor_id');
    }

    private function firstDuesId(string $query = ''): int
    {
        return $this->getJson("/api/v1/dashboard/dues/by-contractor{$query}")->assertOk()->json('items.0.contractor_id');
    }

    public function test_contractor_given_a_new_due_comes_first_in_both_lists(): void
    {
        [, , $least] = $this->seedContractors();

        $this->postJson('/api/v1/dashboard/dues', [
            'contractor_id' => $least->id,
            'description'   => 'رسوم عضوية جديدة',
            'amount_jod'    => 10,
            'year'          => now()->year,
            'due_kind'      => 'new',
            'due_date'      => now()->addMonth()->toDateString(),
        ])->assertCreated();

        $this->assertSame($least->id, $this->firstBalanceId());
        $this->assertSame($least->id, $this->firstDuesId());
    }

    public function test_contractor_given_a_penalty_comes_first_in_both_lists(): void
    {
        [, $middle] = $this->seedContractors();

        $this->postJson('/api/v1/penalties', [
            'contractor_id' => $middle->id, 'reason' => 'غرامة تأخير', 'amount' => 5,
        ])->assertSuccessful();

        $this->assertSame($middle->id, $this->firstBalanceId());
        $this->assertSame($middle->id, $this->firstDuesId());
    }

    public function test_contractor_whose_balance_is_adjusted_comes_first_in_balances(): void
    {
        [, , $least] = $this->seedContractors();

        $this->postJson("/api/v1/dashboard/balances/{$least->id}/adjust", [
            'mode' => 'increase', 'amount' => 20, 'reason' => 'تسوية يدوية',
        ])->assertSuccessful();

        $this->assertSame($least->id, $this->firstBalanceId());
    }

    public function test_explicit_sorts_still_order_by_amount(): void
    {
        [$most, , $least] = $this->seedContractors();

        $this->postJson('/api/v1/penalties', [
            'contractor_id' => $least->id, 'reason' => 'غرامة', 'amount' => 1,
        ])->assertSuccessful();

        $this->assertSame($most->id, $this->firstBalanceId('?sort=asc'));
        $this->assertSame($most->id, $this->firstDuesId('?sort=remaining'));
    }
}
