<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-17 #8 — تسجيل غرامة بحالتها الفعلية بخطوة واحدة.
 *
 * الحالات الأربع كانت موجودة أصلاً (TASK-06)، لكن store() لم يقبل الحالة إطلاقاً: كل غرامة
 * تُنشأ "غير مسدَّدة" ثم تحتاج PATCH لتصحيحها — وهو ما جعل إدراج السجلات الورقية القديمة
 * عملية من خطوتين بلا سبب.
 */
class PenaltyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function contractor(): Contractor
    {
        return Contractor::create([
            'name'                => 'شركة اختبار للمقاولات',
            'membership_number'   => '928_g',
            'commercial_register' => '563960123',
            'status'              => 'active',
            'is_frozen'           => false,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'contractor_id' => $this->contractor()->id,
            'reason'        => 'تأخّر في تسليم مستندات التجديد',
            'amount'        => 500,
        ], $extra);
    }

    public function test_a_penalty_defaults_to_unpaid_when_no_status_is_sent(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/penalties', $this->payload())->assertCreated();

        $penalty = Penalty::findOrFail($response->json('items.id'));
        $this->assertSame('unpaid', $penalty->status);
        $this->assertSame('0.00', $penalty->paid_amount);
        $this->assertNull($penalty->paid_at);
    }

    public function test_a_penalty_can_be_registered_as_paid(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/penalties', $this->payload(['status' => 'paid']))->assertCreated();

        $penalty = Penalty::findOrFail($response->json('items.id'));
        $this->assertSame('paid', $penalty->status);
        $this->assertSame('500.00', $penalty->paid_amount, 'المسدَّد يُشتقّ من مبلغ الغرامة الكامل');
        $this->assertNotNull($penalty->paid_at);
    }

    public function test_a_penalty_can_be_registered_as_partially_paid(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/penalties', $this->payload([
            'status'      => 'partially_paid',
            'paid_amount' => 200,
        ]))->assertCreated();

        $penalty = Penalty::findOrFail($response->json('items.id'));
        $this->assertSame('partially_paid', $penalty->status);
        $this->assertSame('200.00', $penalty->paid_amount);
        $this->assertNull($penalty->paid_at);
    }

    public function test_a_penalty_can_be_registered_as_rejected_with_a_reason(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/penalties', $this->payload([
            'status'        => 'rejected',
            'reject_reason' => 'أُلغيت بقرار مجلس الإدارة',
        ]))->assertCreated();

        $penalty = Penalty::findOrFail($response->json('items.id'));
        $this->assertSame('rejected', $penalty->status);
        $this->assertSame('0.00', $penalty->paid_amount);
        $this->assertSame('أُلغيت بقرار مجلس الإدارة', $penalty->reject_reason);
    }

    public function test_partially_paid_requires_a_paid_amount(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/penalties', $this->payload(['status' => 'partially_paid']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('paid_amount');
    }

    /** نفس الحدّ الذي يفرضه updateStatus — الحالتان لا يجوز أن تتفرّقا. */
    public function test_partially_paid_rejects_a_paid_amount_at_or_above_the_full_amount(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/penalties', $this->payload([
            'status'      => 'partially_paid',
            'paid_amount' => 500,
        ]))->assertStatus(422);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/penalties', $this->payload(['status' => 'cancelled']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /** الصفحة تقرأ items/meta — فهذه الاستجابة هي العقد الذي بُني عليه إصلاح الواجهة. */
    public function test_the_list_returns_items_and_meta(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/penalties', $this->payload())->assertCreated();

        $this->getJson('/api/v1/penalties')
            ->assertOk()
            ->assertJsonStructure(['items', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('meta.total', 1);
    }
}
