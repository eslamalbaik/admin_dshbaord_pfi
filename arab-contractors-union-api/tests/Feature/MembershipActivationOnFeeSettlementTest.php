<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * QA M-03: رسوم الاشتراك السنوية لما تنسدّ كاملة بتفعّل عضوية سنتها (تنتهي 31/12) من أي مسار
 * تسديد، مش بس من صرف الرصيد الدائن. قبل الإصلاح: الدفعة اليدوية خلّت الحالة "فعّالة" بالأرصدة
 * بلا أي عضوية مسجّلة لسنة 2026.
 */
class MembershipActivationOnFeeSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Contractor $contractor;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->contractor = Contractor::create([
            'name'              => 'شركة تفعيل العضوية',
            'membership_number' => '977_g',
            'status'            => 'expired',
            'is_frozen'         => false,
        ]);
    }

    private function feeDue(float $amount = 300, string $description = 'رسوم اشتراك 2026'): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $this->contractor->id,
            'year'          => 2026,
            'description'   => $description,
            'amount_jod'    => $amount,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ]);
    }

    private function activeMemberships2026()
    {
        return Membership::where('contractor_id', $this->contractor->id)
            ->where('status', 'active')
            ->whereYear('expires_at', 2026)
            ->get();
    }

    private function asAdmin(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
    }

    private function manualPayment(float $amount): int
    {
        $this->asAdmin();
        $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id' => $this->contractor->id, 'amount' => $amount, 'currency' => 'JOD', 'method' => 'cash',
        ])->assertSuccessful();

        return (int) \App\Models\Payment::where('contractor_id', $this->contractor->id)->latest('id')->value('id');
    }

    public function test_manual_payment_that_settles_the_fee_activates_the_year(): void
    {
        $this->feeDue();

        $this->manualPayment(300);

        $memberships = $this->activeMemberships2026();
        $this->assertCount(1, $memberships);
        $this->assertSame('2026-12-31', $memberships->first()->expires_at->toDateString());
        $this->assertSame('active', $this->contractor->fresh()->status);
    }

    public function test_partial_manual_payment_does_not_activate(): void
    {
        $this->feeDue();

        $this->manualPayment(200);

        $this->assertCount(0, $this->activeMemberships2026());
    }

    public function test_settling_a_non_fee_due_does_not_activate(): void
    {
        $this->feeDue(300, 'غرامة تأخير قديمة');

        $this->manualPayment(300);

        $this->assertCount(0, $this->activeMemberships2026());
    }

    public function test_confirmed_app_transfer_that_settles_the_fee_activates_the_year(): void
    {
        $this->feeDue();

        Sanctum::actingAs($this->contractor, ['*']);
        $id = $this->postJson('/api/v1/contractor/payments/transfer', [
            'amount' => 300, 'receipt_image' => UploadedFile::fake()->image('r.jpg'),
        ])->assertCreated()->json('items.id');

        $this->asAdmin();
        $this->postJson("/api/v1/payments/transactions/{$id}/confirm")->assertOk();

        $this->assertCount(1, $this->activeMemberships2026());
    }

    public function test_manual_settle_from_the_dues_screen_activates_the_year(): void
    {
        $due = $this->feeDue();

        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/dues/{$due->id}/settle", ['amount_jod' => 300])->assertOk();

        $this->assertCount(1, $this->activeMemberships2026());
    }

    public function test_membership_fee_payment_does_not_create_a_second_membership(): void
    {
        $this->feeDue();

        $this->asAdmin();
        $id = $this->postJson('/api/v1/payments/transactions', [
            'contractor_id' => $this->contractor->id, 'amount' => 300, 'currency' => 'JOD',
            'type' => 'membership_fee', 'status' => 'pending', 'method' => 'cash',
        ])->assertSuccessful()->json('items.id');
        $this->postJson("/api/v1/payments/transactions/{$id}/confirm")->assertOk();

        // renewFromPayment بتجدّد عضويتها، والتفعيل من الذمة ما لازم يضيف عضوية ثانية لنفس السنة
        $this->assertLessThanOrEqual(1, $this->activeMemberships2026()->count());
    }

    public function test_reverting_the_payment_cancels_the_activated_membership(): void
    {
        $this->feeDue();
        $paymentId = $this->manualPayment(300);
        $this->assertCount(1, $this->activeMemberships2026());

        $this->postJson("/api/v1/payments/transactions/{$paymentId}/status", [
            'status' => 'pending', 'reason' => 'اختبار الترجيع',
        ])->assertOk();

        $this->assertCount(0, $this->activeMemberships2026());
    }
}
