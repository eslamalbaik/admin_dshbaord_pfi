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
 * الكسور ما بتنجبر (قاعدة eslam 31/10): الذمم والأرصدة والصافي بتضل بكسورها، وما بيضل أي قيد جبر.
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

    public function test_a_due_keeps_its_fraction(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $this->assertEquals(991.40, (float) $due->fresh()->amount_jod);
        $this->assertSame(1, ContractorDue::where('contractor_id', $c->id)->count());
        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(-991.40, ContractorBalances::exactNet($c->id));
    }

    public function test_creditor_fraction_is_kept(): void
    {
        $c = $this->contractor();
        $credit = ContractorCredit::create(['contractor_id' => $c->id, 'amount_jod' => 181.40, 'used_jod' => 0, 'description' => 'رصيد']);

        $this->assertEquals(181.40, (float) $credit->fresh()->amount_jod);
        $this->assertSame(1, ContractorCredit::where('contractor_id', $c->id)->count());
        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(181.40, ContractorBalances::exactNet($c->id));
    }

    public function test_whole_dinar_balance_gets_no_rounding(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 349);

        $this->assertEquals(349, (float) $due->fresh()->amount_jod);
        $this->assertEquals(0, $this->dueRounding($c));
        $this->assertSame(0, $this->separateEntries($c));
    }

    public function test_half_dinar_debt_is_displayed_as_is(): void
    {
        $c = $this->contractor();
        $this->due($c, 0.50);

        $this->assertEquals(-0.50, ContractorBalances::exactNet($c->id));
        $this->assertEquals(-0.50, (float) ContractorBalances::query()->where('contractors.id', $c->id)->first()->net_jod);
    }

    /** صورة eslam 12/10: 500 + 45.20 + 45.70 بدفعة 599 ← 500 + 46 + 46 والرصيد 7 */
    /** صورة eslam 14/10: ذمتين 500 و89.50 بدفعة 589.50 بيتسددوا كاملين */
    public function test_paying_the_exact_fractional_total_settles_every_due(): void
    {
        $c = $this->contractor();
        $a = $this->due($c, 500);
        $b = $this->due($c, 89.50, 2026);

        $this->pay($c, 589.50);

        $this->assertSame('paid', $a->fresh()->status);
        $this->assertSame('paid', $b->fresh()->status);
        $this->assertEquals(89.50, (float) $b->fresh()->amount_jod);
        $this->assertEquals(0, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, $this->separateEntries($c));
    }

    public function test_partial_payment_leaves_the_exact_remainder(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $this->pay($c, 500);

        $this->assertEquals(991.40, (float) $due->fresh()->amount_jod);
        $this->assertEquals(500, (float) $due->fresh()->paid_jod);
        $this->assertEquals(-491.40, ContractorBalances::exactNet($c->id));
    }

    public function test_paying_the_exact_amount_settles_everything(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->pay($c, 991.40);

        $this->assertEquals(0, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, ContractorDue::where('contractor_id', $c->id)->where('status', '!=', 'paid')->count());
    }

    public function test_overpayment_surplus_keeps_its_fraction(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);

        $this->pay($c, 995); // فائض 3.60 بيضل 3.60

        $this->assertEquals(3.60, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, $this->separateEntries($c));
    }

    /** فرق عملة (سداد بالشيكل زاد 0.18) بيضل رصيد للشركة وبينصرف على الذمة الجديدة */
    public function test_currency_surplus_is_kept_and_applied_to_a_new_due(): void
    {
        $c = $this->contractor();
        $this->due($c, 10);
        $this->pay($c, 10.18);
        $this->assertEquals(0.18, ContractorBalances::exactNet($c->id));

        $this->due($c, 200);
        app(ContractorCreditService::class)->applyAvailableCredit($c->fresh());

        $this->assertEquals(-199.82, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, $this->separateEntries($c));
    }

    public function test_a_fractional_new_due_keeps_its_fraction_after_a_surplus(): void
    {
        $c = $this->contractor();
        $this->due($c, 10);
        $this->pay($c, 10.18);

        $this->due($c, 3.90);
        app(ContractorCreditService::class)->applyAvailableCredit($c->fresh());

        $this->assertEquals(-3.72, ContractorBalances::exactNet($c->id));
        $this->assertSame(0, $this->separateEntries($c));
    }

    public function test_editing_the_due_amount_keeps_the_new_fraction(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 991.40);

        $due->fresh()->update(['amount_jod' => 500.30]);

        $this->assertEquals(500.30, (float) $due->fresh()->amount_jod);
        $this->assertEquals(-500.30, ContractorBalances::exactNet($c->id));
    }

    public function test_discounted_due_keeps_its_fraction(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, 100);

        $due->fresh()->applyDiscount('fixed', 10.50, 'خصم', User::factory()->create()->id);

        $this->assertEquals(89.50, (float) $due->fresh()->amount_jod);
        $this->assertEquals(-89.50, ContractorBalances::exactNet($c->id));
    }

    public function test_legacy_separate_entry_is_removed(): void
    {
        $c = $this->contractor();
        $this->due($c, 991);
        ContractorDue::withoutEvents(fn () => ContractorDue::create([
            'contractor_id' => $c->id, 'description' => BalanceRoundingService::TAG, 'amount_jod' => 0.60,
            'paid_jod' => 0, 'status' => 'unpaid', 'source' => 'manual', 'notes' => BalanceRoundingService::TAG,
        ]));

        app(BalanceRoundingService::class)->sync($c->id);

        $this->assertSame(0, $this->separateEntries($c));
        $this->assertEquals(-991, ContractorBalances::exactNet($c->id));
    }

    public function test_payment_surplus_has_no_rounding(): void
    {
        $c = $this->contractor();
        $this->due($c, 10);
        $this->pay($c, 13.40); // فائض 3.40 بيضل 3.40
        $payment = \App\Models\Payment::where('contractor_id', $c->id)->sole();

        $this->assertEquals(0, (float) $payment->rounding_jod);
        $this->assertEquals(10, (float) $payment->used_amount_jod);
        $this->assertEquals(3.40, ContractorBalances::exactNet($c->id));
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
        ContractorCredit::create(['contractor_id' => $c->id, 'amount_jod' => 181.40, 'used_jod' => 0, 'description' => 'رصيد']);

        $this->assertSame([], app(BalanceRoundingService::class)->syncAll());
        $this->assertEquals(181.40, ContractorBalances::exactNet($c->id));
    }

    public function test_home_last_invoice_shows_the_exact_due(): void
    {
        $c = $this->contractor();
        $this->due($c, 991.40);
        Sanctum::actingAs($c, ['*']);

        $this->getJson('/api/v1/contractor/home')
            ->assertOk()
            ->assertJsonPath('items.financial.last_invoice.amount_jod', '991.40');
    }

    public function test_command_leaves_fractions_alone(): void
    {
        $c = $this->contractor();
        $credit = ContractorCredit::withoutEvents(fn () => ContractorCredit::create([
            'contractor_id' => $c->id, 'amount_jod' => 10.25, 'used_jod' => 0, 'description' => 'رصيد',
        ]));

        $this->artisan('balances:round', ['--dry-run' => true])->assertSuccessful();
        $this->artisan('balances:round')->assertSuccessful();

        $this->assertEquals(10.25, (float) $credit->fresh()->amount_jod);
        $this->assertSame(0, $this->separateEntries($c));
    }


    public function test_migration_restores_the_amounts_that_were_rounded_up(): void
    {
        $c = $this->contractor();
        $due = ContractorDue::withoutEvents(fn () => ContractorDue::create([
            'contractor_id' => $c->id, 'description' => 'هيك', 'year' => 2026,
            'amount_jod' => 90, 'paid_jod' => 89.80, 'status' => 'partially_paid',
        ]));
        \Illuminate\Support\Facades\DB::table('contractor_due_amount_backups')->insert([
            'contractor_due_id' => $due->id, 'old_amount_jod' => 89.50,
        ]);

        (require database_path('migrations/2026_10_14_000001_restore_unrounded_due_amounts.php'))->up();

        $this->assertEquals(89.50, (float) $due->fresh()->amount_jod);
        $this->assertSame('paid', $due->fresh()->status);
    }


    public function test_sync_restores_a_credit_that_was_rounded_down(): void
    {
        $c = $this->contractor();
        // الشكل القديم المخزَّن: 181.40 انجبر لـ181 وrounding_jod = 0.40
        $credit = ContractorCredit::withoutEvents(fn () => ContractorCredit::create([
            'contractor_id' => $c->id, 'amount_jod' => 181, 'rounding_jod' => 0.40, 'used_jod' => 0, 'description' => 'رصيد',
        ]));

        app(BalanceRoundingService::class)->sync($c->id);

        $this->assertEquals(181.40, (float) $credit->fresh()->amount_jod);
        $this->assertEquals(0, (float) $credit->fresh()->rounding_jod);
        $this->assertEquals(181.40, ContractorBalances::exactNet($c->id));
    }
}
