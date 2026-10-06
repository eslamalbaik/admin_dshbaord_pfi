<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "تغيير الحالة" من قائمة الثلاث نقاط بسجل المدفوعات: السبب إجباري وبينحفظ، والدفعة
 * المؤكَّدة اللي إلها أثر (سدّدت ذمة / جدّدت عضوية) ما بترجع إلا بعد إلغاء أثرها.
 */
class PaymentStatusChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin, ['*']);
    }

    private function contractor(): Contractor
    {
        return Contractor::create([
            'name'              => 'شركة اختبار',
            'membership_number' => '950_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function payment(Contractor $c, array $attrs = []): Payment
    {
        return Payment::create(array_merge([
            'contractor_id' => $c->id,
            'amount'        => 100,
            'currency'      => 'JOD',
            'amount_jod'    => 100,
            'type'          => 'dues_payment',
            'status'        => 'pending',
            'method'        => 'bank_transfer',
            'submitted_at'  => now(),
        ], $attrs));
    }

    private function change(Payment $p, string $status, ?string $reason = 'سبب الاختبار')
    {
        return $this->postJson("/api/v1/payments/transactions/{$p->id}/status", array_filter([
            'status' => $status,
            'reason' => $reason,
        ]));
    }

    public function test_reason_is_required(): void
    {
        $p = $this->payment($this->contractor());

        $this->change($p, 'rejected', null)->assertStatus(422);
        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_pending_to_rejected_saves_reason_and_changer(): void
    {
        $p = $this->payment($this->contractor());

        $this->change($p, 'rejected', 'الإشعار غير واضح')
            ->assertOk()
            ->assertJsonPath('items.status', 'rejected')
            ->assertJsonPath('items.last_status_change.reason', 'الإشعار غير واضح')
            ->assertJsonPath('items.last_status_change.from_status', 'pending')
            ->assertJsonPath('items.last_status_change.changed_by', $this->admin->name);

        $this->assertSame('الإشعار غير واضح', $p->fresh()->rejection_reason);
        $this->assertDatabaseHas('payment_status_changes', [
            'payment_id' => $p->id, 'to_status' => 'rejected', 'changed_by' => $this->admin->id,
        ]);
    }

    public function test_rejected_back_to_pending(): void
    {
        $p = $this->payment($this->contractor(), ['status' => 'rejected', 'rejection_reason' => 'x']);

        $this->change($p, 'pending')->assertOk()->assertJsonPath('items.status', 'pending');
        $this->assertNull($p->fresh()->rejection_reason);
    }

    public function test_pending_to_paid_settles_linked_due(): void
    {
        $c   = $this->contractor();
        $due = ContractorDue::create([
            'contractor_id' => $c->id, 'year' => 2026, 'description' => 'رسوم 2026',
            'amount_jod' => 100, 'status' => 'unpaid', 'source' => 'manual',
        ]);
        $p = $this->payment($c, ['contractor_due_id' => $due->id]);

        $this->change($p, 'paid')->assertOk()->assertJsonPath('items.status', 'paid');
        $this->assertSame('paid', $due->fresh()->status);
    }

    public function test_same_status_is_rejected(): void
    {
        $p = $this->payment($this->contractor());

        $this->change($p, 'pending')->assertStatus(422);
    }

    public function test_paid_without_effects_can_be_reverted(): void
    {
        // دفعة مؤكَّدة ما سدّدت شي = رصيد بس؛ ترجيعها بيشيل الرصيد لحاله
        $p = $this->payment($this->contractor(), ['status' => 'paid', 'used_amount_jod' => 0]);

        $this->getJson('/api/v1/payments/transactions')->assertOk()
            ->assertJsonPath('items.0.status_change_blocker', null);

        $this->change($p, 'rejected')->assertOk();
        $this->assertSame('rejected', $p->fresh()->status);
        $this->assertNull($p->fresh()->paid_at);
    }

    public function test_paid_that_settled_dues_is_blocked(): void
    {
        $p = $this->payment($this->contractor(), ['status' => 'paid', 'used_amount_jod' => 50]);

        $blocker = $this->getJson('/api/v1/payments/transactions')->assertOk()
            ->json('items.0.status_change_blocker');
        $this->assertNotNull($blocker);

        $this->change($p, 'rejected')->assertStatus(422);
        $this->change($p, 'pending')->assertStatus(422);
        $this->postJson("/api/v1/payments/transactions/{$p->id}/reject", ['rejection_reason' => 'x'])
            ->assertStatus(422);
        $this->assertSame('paid', $p->fresh()->status);
        $this->assertDatabaseCount('payment_status_changes', 0);
    }

    private function due(Contractor $c, float $amount = 100, int $year = 2026): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $c->id, 'year' => $year, 'description' => "رسوم {$year}",
            'amount_jod' => $amount, 'status' => 'unpaid', 'source' => 'manual',
        ]);
    }

    public function test_linked_transfer_back_to_pending_reverses_due_settlement(): void
    {
        $c   = $this->contractor();
        $due = $this->due($c);
        $p   = $this->payment($c, ['contractor_due_id' => $due->id]);

        $this->change($p, 'paid')->assertOk();
        $this->assertSame('paid', $due->fresh()->status);

        $this->getJson('/api/v1/payments/transactions')->assertOk()
            ->assertJsonPath('items.0.status_change_blocker', null);

        $this->change($p, 'pending', 'تأكيد بالغلط')->assertOk();

        $due->refresh();
        $this->assertSame('unpaid', $due->status);
        $this->assertEquals(0, (float) $due->paid_jod);
        $this->assertEquals(0, (float) $p->fresh()->used_amount_jod);
        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_manual_payment_rejected_reverses_all_dues_it_settled(): void
    {
        $c  = $this->contractor();
        $d1 = $this->due($c, 60, 2025);
        $d2 = $this->due($c, 100, 2026);

        // 100 د.أ: بتسدّد 2025 كاملة (60) و40 من 2026
        $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id' => $c->id, 'amount' => 100, 'currency' => 'JOD', 'method' => 'cash',
        ])->assertStatus(201);

        $this->assertSame('paid', $d1->fresh()->status);
        $this->assertSame('partially_paid', $d2->fresh()->status);

        $p = Payment::latest('id')->first();
        $this->change($p, 'rejected', 'دفعة مكررة')->assertOk();

        $this->assertSame('unpaid', $d1->fresh()->status);
        $this->assertSame('unpaid', $d2->fresh()->status);
        $this->assertEquals(0, (float) $d2->fresh()->paid_jod);
    }

    public function test_settle_from_payment_credit_is_reversed_too(): void
    {
        $c   = $this->contractor();
        $due = $this->due($c, 30);
        $p   = $this->payment($c, ['status' => 'paid', 'used_amount_jod' => 0]);

        $this->postJson("/api/v1/dashboard/dues/{$due->id}/settle", ['payment_id' => $p->id])->assertOk();
        $this->assertSame('paid', $due->fresh()->status);

        // المسار القديم (زر رفض) كمان بيرجّع التسديد
        $this->postJson("/api/v1/payments/transactions/{$p->id}/reject", ['rejection_reason' => 'x'])->assertOk();
        $this->assertSame('unpaid', $due->fresh()->status);
    }

    public function test_membership_fee_revert_cancels_membership_it_created(): void
    {
        $c = $this->contractor();
        $c->update(['status' => 'expired']);
        $p = $this->payment($c, ['type' => 'membership_fee']);

        $this->change($p, 'paid')->assertOk();
        $membership = $p->fresh()->membership;
        $this->assertSame('active', $membership->status);
        $this->assertSame('active', $c->fresh()->status);

        $this->getJson('/api/v1/payments/transactions')->assertOk()
            ->assertJsonPath('items.0.status_change_blocker', null);

        $this->change($p, 'rejected', 'الحوالة ما وصلت')->assertOk();

        $membership->refresh();
        $this->assertSame('rejected', $membership->status);
        $this->assertNull($membership->expires_at);
        $this->assertSame('expired', $c->fresh()->status);
    }

    public function test_membership_fee_revert_restores_existing_renewal_request(): void
    {
        $c          = $this->contractor();
        $membership = $c->memberships()->create(['type' => 'renewal', 'status' => 'pending', 'amount' => 100]);
        $p          = $this->payment($c, ['type' => 'membership_fee', 'membership_id' => $membership->id]);

        $this->change($p, 'paid')->assertOk();
        $this->assertSame('active', $membership->fresh()->status);

        $this->change($p, 'pending', 'تأكيد بالغلط')->assertOk();

        $membership->refresh();
        $this->assertSame('pending', $membership->status);
        $this->assertNull($membership->expires_at);
        $this->assertSame('active', $c->fresh()->status);
    }

    public function test_old_membership_payment_without_snapshot_can_still_be_reverted(): void
    {
        $c          = $this->contractor();
        $membership = $c->memberships()->create([
            'type' => 'renewal', 'status' => 'active', 'starts_at' => '2026-01-01', 'expires_at' => '2026-12-31',
        ]);
        $p = $this->payment($c, ['type' => 'membership_fee', 'status' => 'paid', 'membership_id' => $membership->id]);

        $this->change($p, 'rejected')->assertOk();
        $this->assertSame('rejected', $membership->fresh()->status);
    }

    public function test_confirming_already_paid_payment_is_blocked(): void
    {
        $p = $this->payment($this->contractor(), ['status' => 'paid']);

        $this->postJson("/api/v1/payments/transactions/{$p->id}/confirm")->assertStatus(422);
    }
}
