<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * حساب الذمم بعد الخصم والغرامة والدفعة — جدول الذمم والأرصدة وتطبيق المقاول لازم يتّفقوا:
 * 1) الخصم التاني بينطبق على صافي الذمة بعد الخصم الأول (100 ← 10% = 90 ← 20 د.أ = 70).
 * 2) الغرامة المفتوحة من ضمن "إجمالي الذمم القائمة".
 * 3) الدفعة اليدوية بتسدّد الذمم ثم الغرامات، فما بيضل فائض رصيد له والغرامة مفتوحة.
 */
class DuesDiscountPenaltyCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin, ['*']);
    }

    private function contractor(string $membership = '928_g'): Contractor
    {
        return Contractor::create([
            'name'                => "شركة {$membership} للمقاولات",
            'membership_number'   => $membership,
            'commercial_register' => (string) (500000000 + crc32($membership) % 99999999),
            'status'              => 'active',
            'is_frozen'           => false,
        ]);
    }

    private function due(Contractor $contractor, float $amount): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $contractor->id,
            'year'          => 2026,
            'description'   => 'رسوم عضوية 2026',
            'amount_jod'    => $amount,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ]);
    }

    private function discount(ContractorDue $due, string $type, float $value)
    {
        return $this->postJson("/api/v1/dashboard/dues/{$due->id}/discount", [
            'discount_type'  => $type,
            'discount_value' => $value,
        ])->assertOk();
    }

    private function penalty(Contractor $contractor, float $amount): Penalty
    {
        $this->postJson('/api/v1/penalties', [
            'contractor_id' => $contractor->id,
            'reason'        => 'غرامة تأخير',
            'amount'        => $amount,
        ])->assertCreated();

        return Penalty::where('contractor_id', $contractor->id)->latest('id')->first();
    }

    private function balanceRow(Contractor $contractor): array
    {
        return collect($this->getJson('/api/v1/dashboard/balances?per_page=100')->assertOk()->json('items'))
            ->firstWhere('contractor_id', $contractor->id);
    }

    private function duesRow(Contractor $contractor): array
    {
        return collect($this->getJson('/api/v1/dashboard/dues/by-contractor?per_page=100')->assertOk()->json('items'))
            ->firstWhere('contractor_id', $contractor->id);
    }

    // ─── #1 الخصم ───

    public function test_a_fixed_discount_after_a_percent_one_applies_to_the_discounted_amount(): void
    {
        $due = $this->due($this->contractor(), 200);
        $this->patchJson("/api/v1/dashboard/dues/{$due->id}", ['amount_jod' => 100])->assertOk();

        $this->discount($due, 'percent', 10);
        $this->assertSame('90.00', $due->fresh()->amount_jod);

        $this->discount($due, 'fixed', 20);
        $due->refresh();

        $this->assertSame('70.00', $due->amount_jod);
        $this->assertSame('100.00', $due->original_amount_jod);
        $this->assertSame('30.00', $due->discount_amount_jod);
        $this->assertSame('fixed', $due->discount_type);
        $this->assertStringNotContainsString('بعد خصم', $due->description);
    }

    public function test_a_percent_discount_after_a_fixed_one_applies_to_the_discounted_amount(): void
    {
        $due = $this->due($this->contractor(), 100);

        $this->discount($due, 'fixed', 20);
        $this->discount($due, 'percent', 10);

        $this->assertSame('72.00', $due->fresh()->amount_jod);
        $this->assertSame('28.00', $due->fresh()->discount_amount_jod);
    }

    public function test_a_discount_never_shows_as_credit_in_the_balance(): void
    {
        $contractor = $this->contractor();
        $due = $this->due($contractor, 100);

        $this->discount($due, 'percent', 10);
        $this->discount($due, 'fixed', 20);

        $row = $this->balanceRow($contractor);
        $this->assertEquals(0, $row['credit_jod']);
        $this->assertEquals(-70, $row['net_jod']);
    }

    // ─── #2 الغرامة بإجمالي الذمم ───

    public function test_an_open_penalty_is_added_to_the_outstanding_dues_total(): void
    {
        $contractor = $this->contractor();
        $this->due($contractor, 2781.78);

        $this->assertEquals(2781.78, $this->getJson('/api/v1/dashboard/dues/summary')->json('items.outstanding_total_jod'));

        $this->penalty($contractor, 100);

        $summary = $this->getJson('/api/v1/dashboard/dues/summary')->assertOk()->json('items');
        $this->assertEquals(2881.78, $summary['outstanding_total_jod']);
        $this->assertSame(1, $summary['open_penalties_count']);
        $this->assertSame(1, $summary['contractors_with_dues']);
    }

    public function test_a_rejected_penalty_is_not_counted(): void
    {
        $contractor = $this->contractor();
        $this->due($contractor, 50);
        $penalty = $this->penalty($contractor, 100);
        $this->patchJson("/api/v1/penalties/{$penalty->id}/status", ['status' => 'rejected'])->assertOk();

        $this->assertEquals(50, $this->getJson('/api/v1/dashboard/dues/summary')->json('items.outstanding_total_jod'));
    }

    // ─── #3 الدفعة على الصافي + الغرامة ───

    public function test_a_payment_settles_the_discounted_due_then_the_penalty_and_all_screens_agree(): void
    {
        $contractor = $this->contractor();
        $due = $this->due($contractor, 100);
        $this->discount($due, 'fixed', 20);           // الذمة 80
        $penalty = $this->penalty($contractor, 100);  // + غرامة 100 = 180

        $this->postJson("/api/v1/dashboard/contractors/{$contractor->id}/dues/pay", ['amount' => 100])
            ->assertCreated()
            ->assertJsonPath('items.unapplied_jod', 0);

        $this->assertSame('paid', $due->fresh()->status);
        $this->assertSame('partially_paid', $penalty->fresh()->status);
        $this->assertSame('20.00', $penalty->fresh()->paid_amount);

        $this->assertEquals(80, $this->duesRow($contractor)['remaining_jod']);

        $balance = $this->balanceRow($contractor);
        $this->assertEquals(0, $balance['credit_jod']);
        $this->assertEquals(-80, $balance['net_jod']);

        $this->assertEquals(80, $this->getJson('/api/v1/dashboard/dues/summary')->json('items.outstanding_total_jod'));

        Sanctum::actingAs($contractor, ['*']);
        $this->assertEquals(-80, $this->getJson('/api/v1/contractor/balance')->assertOk()->json('items.net_jod'));
        $financial = $this->getJson('/api/v1/contractor/financial')->assertOk()->json('items.summary');
        $this->assertSame('80.00', $financial['unpaid_penalties']);
        $this->assertSame('80.00', $financial['total_obligations']);
    }

    public function test_reverting_the_payment_reopens_the_penalty_it_settled(): void
    {
        $contractor = $this->contractor();
        $due = $this->due($contractor, 80);
        $penalty = $this->penalty($contractor, 100);

        $this->postJson("/api/v1/dashboard/contractors/{$contractor->id}/dues/pay", ['amount' => 100])->assertCreated();
        $payment = Payment::where('contractor_id', $contractor->id)->latest('id')->first();

        $this->postJson("/api/v1/payments/transactions/{$payment->id}/status", [
            'status' => 'rejected',
            'reason' => 'دفعة مسجّلة بالغلط',
        ])->assertOk();

        $this->assertSame('unpaid', $due->fresh()->status);
        $this->assertSame('unpaid', $penalty->fresh()->status);
        $this->assertSame('0.00', $penalty->fresh()->paid_amount);
        $this->assertEquals(-180, $this->balanceRow($contractor)['net_jod']);
    }
}
