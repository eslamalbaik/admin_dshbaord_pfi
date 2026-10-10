<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * بلاغ 10/10: 3 ذمم × 100 د.أ على نفس المقاول (300)، خصم 20% ← لازم 240 وأثر الخصم 60.
 * كان يطلع أثره 40 والإجمالي 260: المقاول عنده رصيد سابق انصرف تلقائياً على أول ذمة فصارت
 * مسدَّدة، والخصم كان يتخطّى أي ذمة مسدَّدة (لأنه "بينزّلها تحت المسدَّد"). هلأ بتنخصم كمان،
 * والفرق بيرجع رصيد وبينصرف على باقي ذممه، وكل الشاشات بتتّفق.
 */
class DuesPercentDiscountTotalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin, ['*']);
    }

    private function contractor(): Contractor
    {
        return Contractor::create([
            'name'                => 'شركة الاختبار للمقاولات',
            'membership_number'   => '928_g',
            'commercial_register' => '512345678',
            'status'              => 'active',
            'is_frozen'           => false,
        ]);
    }

    /** نفس مسار "إضافة ذمة" بالداشبورد — بيصرف أي رصيد سابق عالذمة فوراً */
    private function addThreeDues(Contractor $contractor): array
    {
        foreach (range(1, 3) as $_) {
            $this->postJson('/api/v1/dashboard/dues', [
                'contractor_id' => $contractor->id,
                'description'   => 'ذمة مالية',
                'amount_jod'    => 100,
                'year'          => 2026,
                'due_date'      => now()->addDay()->toDateString(),
            ])->assertCreated();
        }

        return $contractor->dues()->pluck('id')->all();
    }

    private function withCredit(Contractor $contractor, float $amount): void
    {
        ContractorCredit::create([
            'contractor_id' => $contractor->id,
            'amount_jod'    => $amount,
            'used_jod'      => 0,
            'description'   => 'رصيد سابق',
            'source'        => 'manual',
        ]);
    }

    private function assertAllScreensAgree(Contractor $contractor, float $total, float $outstanding): void
    {
        $this->assertEquals($total, (float) $contractor->dues()->sum('amount_jod'), 'مجموع الذمم بعد الخصم');

        $duesRow = collect($this->getJson('/api/v1/dashboard/dues/by-contractor?per_page=100')->assertOk()->json('items'))
            ->firstWhere('contractor_id', $contractor->id);
        $this->assertEquals($total, (float) $duesRow['total_jod'], 'جدول الذمم: الإجمالي');
        $this->assertEquals($outstanding, (float) $duesRow['remaining_jod'], 'جدول الذمم: المتبقي');

        $balanceRow = collect($this->getJson('/api/v1/dashboard/balances?per_page=100')->assertOk()->json('items'))
            ->firstWhere('contractor_id', $contractor->id);
        $this->assertEquals(-$outstanding, (float) $balanceRow['net_jod'], 'صفحة الأرصدة: الصافي');

        Sanctum::actingAs($contractor, ['*']);
        $this->assertEquals($outstanding, (float) $this->getJson('/api/v1/contractor/balance')->assertOk()
            ->json('items.amount_due_jod'), 'التطبيق: المطلوب دفعه');
        Sanctum::actingAs($this->admin, ['*']);
    }

    public function test_twenty_percent_on_three_unpaid_dues_gives_240(): void
    {
        $contractor = $this->contractor();
        $ids = $this->addThreeDues($contractor);

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode' => 'ids', 'ids' => $ids, 'discount_type' => 'percent', 'discount_value' => 20, 'dry_run' => true,
        ])->assertOk()->assertJsonPath('items.total_discount_impact_jod', 60);

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode' => 'ids', 'ids' => $ids, 'discount_type' => 'percent', 'discount_value' => 20,
        ])->assertOk()->assertJsonPath('items.applied_count', 3);

        $this->assertAllScreensAgree($contractor, 240, 240);
    }

    /** السيناريو اللي طلّع 260: رصيد سابق 100 سدّد أول ذمة بالكامل */
    public function test_a_due_already_paid_from_credit_is_discounted_too(): void
    {
        $contractor = $this->contractor();
        $this->withCredit($contractor, 100);
        $ids = $this->addThreeDues($contractor);

        $this->assertSame(1, $contractor->dues()->where('status', 'paid')->count(), 'الرصيد سدّد أول ذمة');

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode' => 'ids', 'ids' => $ids, 'discount_type' => 'percent', 'discount_value' => 20, 'dry_run' => true,
        ])->assertOk()
            ->assertJsonPath('items.applicable_count', 3)
            ->assertJsonPath('items.total_discount_impact_jod', 60)
            ->assertJsonPath('items.refund_to_credit_jod', 20);

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode' => 'ids', 'ids' => $ids, 'discount_type' => 'percent', 'discount_value' => 20,
        ])->assertOk()->assertJsonPath('items.applied_count', 3);

        $dues = $contractor->dues()->orderBy('id')->get();
        $this->assertEquals([80, 80, 80], $dues->pluck('amount_jod')->map(fn ($v) => (float) $v)->all());
        // الـ20 اللي زادت عن أول ذمة انصرفت على التانية
        $this->assertEquals([80, 20, 0], $dues->pluck('paid_jod')->map(fn ($v) => (float) $v)->all());

        // 240 بعد الخصم − 100 رصيد = 140 مطلوبة، ولا قرش رصيد ضايع أو مكرّر
        $this->assertAllScreensAgree($contractor, 240, 140);
        $this->assertEquals(100, (float) ContractorCredit::where('contractor_id', $contractor->id)->sum('used_jod'));
    }

    public function test_criteria_mode_on_the_contractor_gives_the_same_240(): void
    {
        $contractor = $this->contractor();
        $this->withCredit($contractor, 100);
        $this->addThreeDues($contractor);

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode'           => 'criteria',
            'criteria'       => ['contractor_ids' => [$contractor->id]],
            'discount_type'  => 'percent',
            'discount_value' => 20,
        ])->assertOk()->assertJsonPath('items.applied_count', 3);

        $this->assertAllScreensAgree($contractor, 240, 140);
    }

    public function test_single_due_discount_on_a_paid_due_refunds_the_difference(): void
    {
        $contractor = $this->contractor();
        $this->withCredit($contractor, 100);
        $ids = $this->addThreeDues($contractor);

        foreach ($ids as $id) {
            $this->postJson("/api/v1/dashboard/dues/{$id}/discount", [
                'discount_type' => 'percent', 'discount_value' => 20,
            ])->assertOk();
        }

        $this->assertAllScreensAgree($contractor, 240, 140);
    }

    /** الرصيد من دفعة مؤكَّدة: الفرق بيرجع للدفعة نفسها، وترجيع الدفعة بعدين بيضل شغّال وصحيح */
    public function test_refund_returns_to_the_paying_payment_and_the_payment_can_still_be_reverted(): void
    {
        $contractor = $this->contractor();
        $payment = Payment::create([
            'contractor_id' => $contractor->id,
            'amount'        => 100,
            'currency'      => 'JOD',
            'type'          => 'advance_payment',
            'status'        => 'pending',
            'method'        => 'bank_transfer',
            'submitted_at'  => now(),
        ]);
        $this->postJson("/api/v1/payments/transactions/{$payment->id}/confirm")->assertOk();

        $ids = $this->addThreeDues($contractor);
        $this->assertEquals(100, (float) $payment->fresh()->used_amount_jod);

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode' => 'ids', 'ids' => $ids, 'discount_type' => 'percent', 'discount_value' => 20,
        ])->assertOk();

        $payment->refresh();
        $this->assertEquals(100, (float) $payment->used_amount_jod, 'الفرق رجع للدفعة وانصرف على الذمة التانية');
        $this->assertEquals(100, (float) $payment->allocations()->sum('amount_jod'), 'التوزيع بيساوي المستخدم');
        $this->assertAllScreensAgree($contractor, 240, 140);

        $this->postJson("/api/v1/payments/transactions/{$payment->id}/status", [
            'status' => 'rejected', 'reason' => 'اختبار ترجيع',
        ])->assertOk();

        $this->assertAllScreensAgree($contractor, 240, 240);
    }
}
