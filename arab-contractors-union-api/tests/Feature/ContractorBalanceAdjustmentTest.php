<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Contractor;
use App\Models\ContractorBalanceAdjustment;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST dashboard/balances/{contractor}/adjust — تعديل رصيد مقاول يدوياً من صفحة الأرصدة.
 */
class ContractorBalanceAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'المدير']);
        Sanctum::actingAs($this->admin, ['*']);
    }

    private function contractor(string $membership = '960_g'): Contractor
    {
        return Contractor::create([
            'name' => "شركة {$membership}", 'membership_number' => $membership,
            'status' => 'active', 'is_frozen' => false,
        ]);
    }

    private function net(Contractor $c): float
    {
        return (float) $this->getJson("/api/v1/dashboard/balances?search={$c->membership_number}")
            ->assertOk()->json('items.0.net_jod');
    }

    public function test_increase_adds_credit_and_logs_before_after(): void
    {
        $c = $this->contractor();

        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", [
            'mode' => 'increase', 'amount' => 25.5, 'reason' => 'تصحيح رصيد قديم',
        ])->assertOk()
            ->assertJsonPath('items.adjustment.balance_before_jod', 0)
            ->assertJsonPath('items.adjustment.balance_after_jod', 25.5)
            ->assertJsonPath('items.adjustment.created_by', 'المدير')
            ->assertJsonPath('items.balance.net_jod', 25.5);

        $this->assertEquals(25.5, $this->net($c));
        $this->assertSame(1, ContractorCredit::where('contractor_id', $c->id)->count());

        $log = ActivityLog::where('action', 'balance.adjusted')->sole();
        $this->assertTrue($log->is_critical);
        $this->assertSame('تصحيح رصيد قديم', $log->meta['reason']);
        $this->assertEquals(0, $log->meta['before']['net_jod']);
        $this->assertEquals(25.5, $log->meta['after']['net_jod']);
    }

    public function test_increase_settles_open_dues_and_status_follows_balance(): void
    {
        $c = $this->contractor();
        $due = ContractorDue::create([
            'contractor_id' => $c->id, 'description' => 'رسوم 2025', 'year' => 2025,
            'amount_jod' => 100, 'paid_jod' => 0, 'status' => 'unpaid',
        ]);
        $this->assertEquals(-100, $this->net($c));

        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", [
            'mode' => 'increase', 'amount' => 100, 'reason' => 'تسوية متفق عليها',
        ])->assertOk()
            ->assertJsonPath('items.adjustment.status_before', 'expired')
            ->assertJsonPath('items.adjustment.status_after', 'active');

        $this->assertEquals(0, $this->net($c));
        $this->assertSame('paid', $due->fresh()->status);
    }

    public function test_decrease_creates_due_and_consumes_existing_credit(): void
    {
        $c = $this->contractor();
        ContractorCredit::create(['contractor_id' => $c->id, 'amount_jod' => 50, 'used_jod' => 0, 'description' => 'رصيد']);

        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", [
            'mode' => 'decrease', 'amount' => 80, 'reason' => 'رصيد مسجّل بالغلط',
        ])->assertOk()->assertJsonPath('items.balance.net_jod', -30);

        $adj = ContractorBalanceAdjustment::sole();
        $this->assertEquals(-80, (float) $adj->amount_jod);
        $due = ContractorDue::findOrFail($adj->contractor_due_id);
        $this->assertSame('تعديل رصيد يدوي', $due->description);
        $this->assertEquals(80, (float) $due->amount_jod);
        $this->assertEquals(-30, $this->net($c));
    }

    public function test_set_moves_balance_to_the_target_in_both_directions(): void
    {
        $c = $this->contractor();
        Payment::create([
            'contractor_id' => $c->id, 'amount' => 40, 'currency' => 'JOD', 'amount_jod' => 40,
            'used_amount_jod' => 0, 'type' => 'dues_payment', 'status' => 'paid', 'method' => 'cash',
        ]);

        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", ['mode' => 'set', 'amount' => -15, 'reason' => 'مطابقة كشف'])
            ->assertOk()->assertJsonPath('items.balance.net_jod', -15);
        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", ['mode' => 'set', 'amount' => 10, 'reason' => 'مطابقة كشف'])
            ->assertOk()->assertJsonPath('items.balance.net_jod', 10);

        $this->assertEquals(10, $this->net($c));
        // الدفعة الأصلية ما انلمست مبلغها
        $this->assertEquals(40, (float) Payment::sole()->amount_jod);

        $this->getJson("/api/v1/dashboard/balances/{$c->id}/adjustments")
            ->assertOk()->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.balance_after_jod', 10)
            ->assertJsonPath('items.0.mode_label', 'تحديد رصيد جديد');
    }

    public function test_validation_reason_required_and_no_op_rejected(): void
    {
        $c = $this->contractor();

        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", ['mode' => 'increase', 'amount' => 5])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", ['mode' => 'decrease', 'amount' => 0, 'reason' => 'سبب'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", ['mode' => 'set', 'amount' => 0, 'reason' => 'سبب واضح'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        $this->assertSame(0, ContractorBalanceAdjustment::count());
    }

    public function test_supervisor_needs_balances_update_permission(): void
    {
        $c = $this->contractor();
        $viewer = User::factory()->create(['role' => 'supervisor', 'permissions' => ['finance.balances.view'], 'is_active' => true]);
        Sanctum::actingAs($viewer, ['*']);

        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", ['mode' => 'increase', 'amount' => 5, 'reason' => 'سبب واضح'])
            ->assertStatus(403);

        $editor = User::factory()->create(['role' => 'supervisor', 'permissions' => ['finance.balances.view', 'finance.balances.update'], 'is_active' => true]);
        Sanctum::actingAs($editor, ['*']);

        $this->postJson("/api/v1/dashboard/balances/{$c->id}/adjust", ['mode' => 'increase', 'amount' => 5, 'reason' => 'سبب واضح'])
            ->assertOk();
    }
}
