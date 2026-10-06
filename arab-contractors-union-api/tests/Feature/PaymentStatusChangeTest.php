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

    public function test_confirming_already_paid_payment_is_blocked(): void
    {
        $p = $this->payment($this->contractor(), ['status' => 'paid']);

        $this->postJson("/api/v1/payments/transactions/{$p->id}/confirm")->assertStatus(422);
    }
}
