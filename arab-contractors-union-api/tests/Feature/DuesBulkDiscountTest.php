<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-17 #10 — الخصم الجماعي: نطاق المطابقة ومصداقية المعاينة.
 *
 * كانت أربع عيوب متراكبة تُنتج شكوى واحدة ("العدد المطابق 6 بدل 2، والأثر 600 بدل 150"):
 * مود المعايير بلا حصر بالمقاولين، وكائن معايير فارغ يطابق كل ذمم النظام، ومعاينة تحسب
 * بمعادلة غير معادلة التطبيق، وعدّ ذمم سيرفضها التطبيق ضمن "المطابقة" مع أثرها الكامل.
 */
class DuesBulkDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function contractor(string $membership): Contractor
    {
        return Contractor::create([
            'name'                => "شركة {$membership} للمقاولات",
            'membership_number'   => $membership,
            'commercial_register' => (string) (500000000 + crc32($membership) % 99999999),
            'status'              => 'active',
            'is_frozen'           => false,
        ]);
    }

    private function due(Contractor $contractor, float $amount, array $extra = []): ContractorDue
    {
        return ContractorDue::create(array_merge([
            'contractor_id' => $contractor->id,
            'year'          => 2026,
            'description'   => 'رسوم عضوية 2026',
            'amount_jod'    => $amount,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ], $extra));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  الحصر — العيب الذي جعل ذمّتين تظهران كستّ
    // ─────────────────────────────────────────────────────────────────────────

    public function test_criteria_mode_is_scoped_to_the_chosen_contractors(): void
    {
        $this->actingAsAdmin();

        $target = $this->contractor('928_g');
        $other  = $this->contractor('929_g');

        $this->due($target, 100);
        $this->due($target, 100);
        // ذمم مقاول آخر بنفس السنة — كانت تُطابَق أيضاً لأن مود المعايير لم يعرف المقاولين
        $this->due($other, 100);
        $this->due($other, 100);
        $this->due($other, 100);
        $this->due($other, 100);

        $response = $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode'           => 'criteria',
            'criteria'       => ['year' => 2026, 'contractor_ids' => [$target->id]],
            'discount_type'  => 'fixed',
            'discount_value' => 75,
            'dry_run'        => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('items.matched_count', 2)
            ->assertJsonPath('items.applicable_count', 2)
            ->assertJsonPath('items.contractors_count', 1)
            ->assertJsonPath('items.total_discount_impact_jod', 150);
    }

    public function test_criteria_mode_rejects_an_empty_criteria_object(): void
    {
        $this->actingAsAdmin();

        $contractor = $this->contractor('928_g');
        $this->due($contractor, 100);

        // كائن معايير فارغ كان يمرّ بالتحقق ثم يطابق كل ذمة بقاعدة البيانات، فخصم 100%
        // بلا معايير كان يصفّر ذمم كل المقاولين دفعة واحدة.
        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode'           => 'criteria',
            'criteria'       => [],
            'discount_type'  => 'percent',
            'discount_value' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('criteria');

        $this->assertSame('100.00', $contractor->dues()->first()->amount_jod);
    }

    public function test_criteria_mode_refuses_the_paid_status_filter(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode'           => 'criteria',
            'criteria'       => ['status' => 'paid'],
            'discount_type'  => 'percent',
            'discount_value' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('criteria.status');
    }

    public function test_criteria_mode_includes_fully_paid_dues_and_refunds_the_difference(): void
    {
        $this->actingAsAdmin();

        $contractor = $this->contractor('928_g');
        $this->due($contractor, 100);
        $this->due($contractor, 100, ['paid_jod' => 100, 'status' => 'paid']);

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode'           => 'criteria',
            'criteria'       => ['contractor_ids' => [$contractor->id]],
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'dry_run'        => true,
        ])->assertOk()
            ->assertJsonPath('items.matched_count', 2)
            ->assertJsonPath('items.applicable_count', 2)
            ->assertJsonPath('items.total_discount_impact_jod', 20)
            ->assertJsonPath('items.refund_to_credit_jod', 10);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  المعاينة تساوي التطبيق
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * الخصم من نفس النوع يتراكم على الخصم السابق داخل applyDiscount()، بينما المعاينة
     * كانت تحسب `original - value` — فكان الأثر المطبَّق يتجاوز المعروض لكل ذمة سبق خصمها.
     */
    public function test_preview_matches_apply_on_an_already_discounted_due(): void
    {
        $this->actingAsAdmin();

        $contractor = $this->contractor('928_g');
        $due = $this->due($contractor, 100);
        $due->applyDiscount('percent', 30, 'خصم أول', User::factory()->create(['role' => 'admin'])->id);

        $payload = [
            'mode'           => 'ids',
            'ids'            => [$due->id],
            'discount_type'  => 'percent',
            'discount_value' => 30,
        ];

        $preview = $this->postJson('/api/v1/dashboard/dues/discount/bulk', $payload + ['dry_run' => true])
            ->assertOk()->json('items');

        $this->postJson('/api/v1/dashboard/dues/discount/bulk', $payload)->assertOk();

        // 30% ثم 30% = 60% إجماليّاً على المبلغ الأصلي، لا 30% مرتين على المخصوم
        $this->assertSame(60.0, (float) $preview['total_discount_impact_jod']);
        $this->assertSame('40.00', $due->fresh()->amount_jod);
    }

    /**
     * الذمة اللي انسدّ عليها أكتر من مبلغها بعد الخصم ما عادت تُتخطّى: بتنخصم والفرق بيرجع
     * رصيد للمقاول وبينصرف على ذممه المفتوحة. المعاينة بتعرض نفس الأثر اللي بيتطبّق.
     */
    public function test_preview_and_apply_agree_on_a_partially_paid_due(): void
    {
        $this->actingAsAdmin();

        $contractor = $this->contractor('928_g');
        $clean   = $this->due($contractor, 100);
        $overpaid = $this->due($contractor, 100, ['paid_jod' => 90, 'status' => 'partially_paid']);

        $payload = [
            'mode'           => 'ids',
            'ids'            => [$clean->id, $overpaid->id],
            'discount_type'  => 'percent',
            'discount_value' => 50,
        ];

        $preview = $this->postJson('/api/v1/dashboard/dues/discount/bulk', $payload + ['dry_run' => true])->assertOk()->json('items');
        $this->assertSame(2, $preview['applicable_count']);
        $this->assertSame(100.0, (float) $preview['total_discount_impact_jod']);
        $this->assertSame(40.0, (float) $preview['refund_to_credit_jod']);
        $this->assertSame([], $preview['skipped']);

        $applied = $this->postJson('/api/v1/dashboard/dues/discount/bulk', $payload)->assertOk()->json('items');
        $this->assertSame(2, $applied['applied_count']);

        // 50 مسدَّدة كلياً، والـ40 الزايدة انصرفت على الذمة التانية (50 ← متبقي 10)
        $this->assertSame('50.00', $overpaid->fresh()->amount_jod);
        $this->assertSame('50.00', $overpaid->fresh()->paid_jod);
        $this->assertSame('paid', $overpaid->fresh()->status);
        $this->assertSame('50.00', $clean->fresh()->amount_jod);
        $this->assertSame('40.00', $clean->fresh()->paid_jod);
    }

    public function test_negative_or_over_100_percent_discount_is_rejected(): void
    {
        $this->actingAsAdmin();

        $contractor = $this->contractor('929_g');
        $due = $this->due($contractor, 100);

        foreach ([-110, 110] as $value) {
            $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
                'mode'           => 'ids',
                'ids'            => [$due->id],
                'discount_type'  => 'percent',
                'discount_value' => $value,
            ])->assertStatus(422)->assertJsonValidationErrors('discount_value');
        }

        $this->assertSame('100.00', $due->fresh()->amount_jod);
    }
}
