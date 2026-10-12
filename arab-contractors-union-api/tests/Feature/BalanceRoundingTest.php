<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\User;
use App\Services\BalanceRoundingService;
use App\Services\ContractorCreditService;
use App\Services\DuesPaymentService;
use App\Support\ContractorBalances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * جبر كسور رصيد المقاول لدينار صحيح لصالح الاتحاد — مضاف على قيمة ذمة أو رصيد موجود، بلا قيد منفصل.
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

    /** قيود "جبر كسور الرصيد" المنفصلة — المفروض ما يضل منها شي */
    private function separateEntries(Contractor $c): int
    {
        return ContractorDue::where('contractor_id', $c->id)->where('notes', BalanceRoundingService::TAG)->count()
            + ContractorCredit::where('contractor_id', $c->id)->where('notes', BalanceRoundingService::TAG)->count();
    }

    private function dueRounding(Contractor $c): float
    {
        return round((float) ContractorDue::where('contractor_id', $c->id)->sum('rounding_jod'), 2);
    }

    private function pay(Contractor $c, float $jod): array
    {
        return app(DuesPaymentService::class)->processPayment($c, ['amount' => $jod, 'currency' => 'JOD'], User::factory()->create()->id);
    }

    public function test_debtor_fraction_is_added_to_the_due_itself(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $this->assertEquals(992, (float) $due->fresh()->amount_jod);
        $this->assertEquals(0.60, (float) $due->fresh()->rounding_jod);
        $this->assertSame(1, ContractorDue::where('contractor_id', $c->id)->count());
        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(-992, ContractorBalances::exactNet($c->id));
    }

    public function test_creditor_fraction_is_taken_off_the_credit_itself(): void
    {
        $c = $this->contractor();
        $credit = ContractorCredit::create(['contractor_id' => $c->id, 'amount_jod' => 181.40, 'used_jod' => 0, 'description' => 'رصيد']);

        $this->assertEquals(181, (float) $credit->fresh()->amount_jod);
        $this->assertSame(1, ContractorCredit::where('contractor_id', $c->id)->count());
        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(181, ContractorBalances::exactNet($c->id));
    }

    public function test_whole_dinar_balance_gets_no_rounding(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 349);

        $this->assertEquals(349, (float) $due->fresh()->amount_jod);
        $this->assertEquals(0, $this->dueRounding($c));
        $this->assertSame(0, $this->separateEntries($c));
    }

    public function test_half_dinar_debt_rounds_up_to_one(): void
    {
        $c = $this->contractor();
        $this->due($c, 0.50);

        $this->assertEquals(-1, ContractorBalances::exactNet($c->id));
    }

    public function test_fraction_goes_on_the_newest_due_only(): void
    {
        $c = $this->contractor();
        $old = $this->due($c, 100.30, 2025);
        $new = $this->due($c, 200.30, 2026);

        $this->assertEquals(100.30, (float) $old->fresh()->amount_jod);
        $this->assertEquals(200.70, (float) $new->fresh()->amount_jod); // 300.60 ← 301
        $this->assertEquals(-301, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, $this->separateEntries($c));

        // ذمة أحدث بمبلغ صحيح: الكسر بينتقل عليها
        $newest = $this->due($c, 50, 2027);
        $this->assertEquals(200.30, (float) $new->fresh()->amount_jod);
        $this->assertEquals(50.40, (float) $newest->fresh()->amount_jod);
        $this->assertEquals(-351, ContractorBalances::exactNet($c->id));
    }

    public function test_partial_payment_keeps_the_rounding_on_the_same_due(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $this->pay($c, 500);

        $this->assertEquals(992, (float) $due->fresh()->amount_jod);
        $this->assertEquals(500, (float) $due->fresh()->paid_jod);
        $this->assertSame(0, $this->separateEntries($c));
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

    public function test_overpayment_surplus_is_rounded_down_without_a_movement(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->pay($c, 995.60); // فائض 3.60 بعد 992

        $this->assertEquals(3, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, $this->separateEntries($c));
        $this->assertSame(0, ContractorCredit::where('contractor_id', $c->id)->count());
    }

    /** فرق عملة (سداد بالشيكل زاد 0.18) انشطب بالجبر: الذمة الجديدة بتضل 200 كاملة، بلا جبر 0.18 عليها */
    public function test_absorbed_surplus_is_not_revived_by_a_new_due(): void
    {
        $c = $this->contractor();
        $this->due($c, 10);
        $this->pay($c, 10.18);
        $this->assertEquals(0, ContractorBalances::exactNet($c->id));

        $new = $this->due($c, 200);
        app(ContractorCreditService::class)->applyAvailableCredit($c->fresh());

        $this->assertEquals(0, (float) $new->fresh()->paid_jod);
        $this->assertEquals(200, (float) $new->fresh()->amount_jod);
        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(-200, ContractorBalances::exactNet($c->id));
    }

    public function test_absorbed_surplus_still_rounds_a_fractional_new_due_to_a_whole_dinar(): void
    {
        $c = $this->contractor();
        $this->due($c, 10);
        $this->pay($c, 10.18);

        $this->due($c, 3.90);
        app(ContractorCreditService::class)->applyAvailableCredit($c->fresh());

        $this->assertEquals(-4, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, $this->separateEntries($c));
    }

    public function test_editing_the_due_amount_drops_the_old_rounding(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $due->fresh()->update(['amount_jod' => 500.30]);

        $this->assertEquals(501, (float) $due->fresh()->amount_jod);
        $this->assertEquals(0.70, (float) $due->fresh()->rounding_jod);
        $this->assertEquals(-501, ContractorBalances::exactNet($c->id));
    }

    public function test_discount_is_computed_on_the_due_value_without_the_rounding(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 99.50); // ← 100

        $due->fresh()->applyDiscount('fixed', 10, 'خصم', User::factory()->create()->id);

        $this->assertEquals(99.50, (float) $due->fresh()->original_amount_jod);
        $this->assertEquals(90, (float) $due->fresh()->amount_jod); // 89.50 ← 90
        $this->assertEquals(-90, ContractorBalances::exactNet($c->id));
    }

    public function test_legacy_separate_entry_is_folded_into_the_due(): void
    {
        $c = $this->contractor();
        $due = ContractorDue::withoutEvents(fn () => $this->due($c, 991.40));
        ContractorDue::withoutEvents(fn () => ContractorDue::create([
            'contractor_id' => $c->id, 'description' => BalanceRoundingService::TAG, 'amount_jod' => 0.60,
            'paid_jod' => 0, 'status' => 'unpaid', 'source' => 'manual', 'notes' => BalanceRoundingService::TAG,
        ]));

        app(BalanceRoundingService::class)->sync($c->id);

        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(992, (float) $due->fresh()->amount_jod);
        $this->assertEquals(-992, ContractorBalances::exactNet($c->id));
    }

    public function test_reverting_the_host_payment_releases_the_fraction(): void
    {
        $c = $this->contractor();
        $this->due($c, 10);
        $this->pay($c, 13.40); // فائض 3.40 ← 3
        $payment = \App\Models\Payment::where('contractor_id', $c->id)->sole();
        $this->assertEquals(0.40, (float) $payment->rounding_jod);
        $this->assertNull(app(\App\Services\PaymentConfirmationService::class)->revertBlocker($payment));

        $payment->update(['status' => 'rejected']); // بس الحالة — الرصيد ما عاد محسوب

        $this->assertEquals(0, (float) $payment->fresh()->rounding_jod);
        $this->assertEquals(10, (float) $payment->fresh()->used_amount_jod);
    }

    public function test_app_due_title_drops_the_discount_suffix(): void
    {
        $c = $this->contractor();
        ContractorDue::create([
            'contractor_id' => $c->id, 'description' => 'رسوم عضوية (بعد خصم 40 د.أ)', 'year' => 2026,
            'amount_jod' => 160, 'paid_jod' => 0, 'status' => 'unpaid',
        ]);
        Sanctum::actingAs($c, ['*']);

        $this->getJson('/api/v1/contractor/financial')
            ->assertOk()
            ->assertJsonPath('items.dues.0.description', 'رسوم عضوية');
    }

    public function test_deleting_the_debt_removes_the_rounding(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $due->delete();

        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(0, ContractorBalances::exactNet($c->id));
    }

    public function test_sync_is_idempotent(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->assertSame([], app(BalanceRoundingService::class)->syncAll());
        $this->assertEquals(0.60, $this->dueRounding($c));
    }

    public function test_home_last_invoice_shows_the_rounded_due(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);
        Sanctum::actingAs($c, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertOk()
            ->assertJsonPath('items.financial.last_invoice.amount_jod', '992.00');
    }

    public function test_command_dry_run_writes_nothing(): void
    {
        $c = $this->contractor();
        // مبلغ بكسور بدون أحداث موديل — زي ما بيصير بتعديل جماعي
        $due = ContractorDue::withoutEvents(fn () => $this->due($c, 10.25));

        $this->artisan('balances:round', ['--dry-run' => true])->assertSuccessful();
        $this->assertEquals(10.25, (float) $due->fresh()->amount_jod);

        $this->artisan('balances:round')->assertSuccessful();
        $this->assertEquals(11, (float) $due->fresh()->amount_jod);
        $this->assertSame(0, $this->separateEntries($c));
    }
}
