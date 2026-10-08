<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\User;
use App\Services\BalanceRoundingService;
use App\Services\DuesPaymentService;
use App\Support\ContractorBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * جبر كسور رصيد المقاول لدينار صحيح لصالح الاتحاد — بقيد مخزَّن "جبر كسور الرصيد".
 */
class BalanceRoundingTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $membership = '950_g'): Contractor
    {
        return Contractor::create([
            'name' => "شركة {$membership}", 'membership_number' => $membership,
            'status' => 'active', 'is_frozen' => false,
        ]);
    }

    private function due(Contractor $c, float $amount, ?int $year = 2026): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $c->id, 'description' => 'رصيد مستحق', 'year' => $year,
            'amount_jod' => $amount, 'paid_jod' => 0, 'status' => 'unpaid',
        ]);
    }

    private function roundingDues(Contractor $c)
    {
        return ContractorDue::where('contractor_id', $c->id)->where('notes', BalanceRoundingService::TAG)->get();
    }

    private function roundingCredits(Contractor $c)
    {
        return ContractorCredit::where('contractor_id', $c->id)->where('notes', BalanceRoundingService::TAG)->get();
    }

    private function pay(Contractor $c, float $jod): array
    {
        return app(DuesPaymentService::class)->processPayment($c, ['amount' => $jod, 'currency' => 'JOD'], User::factory()->create()->id);
    }

    public function test_debtor_fraction_is_stored_as_a_rounding_due(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->assertEquals([0.60], $this->roundingDues($c)->pluck('amount_jod')->map(fn ($v) => (float) $v)->all());
        $this->assertCount(0, $this->roundingCredits($c));
        $this->assertEquals(-992, ContractorBalances::exactNet($c->id));
    }

    public function test_creditor_fraction_is_taken_off_the_credit(): void
    {
        $c = $this->contractor();
        ContractorCredit::create(['contractor_id' => $c->id, 'amount_jod' => 181.40, 'used_jod' => 0, 'description' => 'رصيد']);

        $this->assertEquals([-0.40], $this->roundingCredits($c)->pluck('amount_jod')->map(fn ($v) => (float) $v)->all());
        $this->assertCount(0, $this->roundingDues($c));
        $this->assertEquals(181, ContractorBalances::exactNet($c->id));
    }

    public function test_whole_dinar_balance_gets_no_rounding_entry(): void
    {
        $c = $this->contractor();
        $this->due($c, 349);

        $this->assertCount(0, $this->roundingDues($c));
        $this->assertCount(0, $this->roundingCredits($c));
    }

    public function test_half_dinar_debt_rounds_up_to_one(): void
    {
        $c = $this->contractor();
        $this->due($c, 0.50);

        $this->assertEquals(-1, ContractorBalances::exactNet($c->id));
    }

    public function test_partial_payment_keeps_a_single_rounding_due(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->pay($c, 500);

        $this->assertCount(1, $this->roundingDues($c));
        $this->assertEquals(-492, ContractorBalances::exactNet($c->id));
    }

    public function test_paying_the_rounded_amount_settles_everything(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->pay($c, 992);

        $this->assertEquals(0, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, ContractorDue::where('contractor_id', $c->id)->where('status', '!=', 'paid')->count());
    }

    public function test_overpayment_surplus_is_rounded_down(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->pay($c, 995.60); // فائض 3.60 بعد 992

        $this->assertEquals(3, ContractorBalances::exactNet($c->id));
    }

    public function test_deleting_the_debt_removes_the_rounding_entry(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $due->delete();

        $this->assertCount(0, $this->roundingDues($c));
        $this->assertEquals(0, ContractorBalances::exactNet($c->id));
    }

    public function test_sync_is_idempotent(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->assertSame([], app(BalanceRoundingService::class)->syncAll());
        $this->assertCount(1, $this->roundingDues($c));
    }

    public function test_rounding_due_is_not_shown_as_the_last_invoice(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);
        Sanctum::actingAs($c, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertOk()
            ->assertJsonPath('items.financial.last_invoice.amount_jod', '991.40');
    }

    public function test_command_dry_run_writes_nothing(): void
    {
        $c = $this->contractor();
        // مبلغ بكسور بدون أحداث موديل — زي ما بيصير بتعديل جماعي
        ContractorDue::withoutEvents(fn () => $this->due($c, 10.25));

        $this->artisan('balances:round', ['--dry-run' => true])->assertSuccessful();
        $this->assertCount(0, $this->roundingDues($c));

        $this->artisan('balances:round')->assertSuccessful();
        $this->assertEquals([0.75], $this->roundingDues($c)->pluck('amount_jod')->map(fn ($v) => (float) $v)->all());
    }
}
