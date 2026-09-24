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

    public function test_criteria_mode_excludes_fully_paid_dues_from_the_matched_set(): void
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
        ])->assertOk()->assertJsonPath('items.matched_count', 1);
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
     * الذمة التي سيُنزلها الخصم تحت المسدَّد فعلاً تُرفض عند التطبيق. كانت المعاينة تعدّها
     * "مطابقة" وتضيف أثرها الكامل للإجمالي، فيظهر للمستخدم رقم لا يتحقّق أبداً.
     */
    public function test_preview_reports_unapplicable_dues_as_skipped_not_matched(): void
    {
        $this->actingAsAdmin();

        $contractor = $this->contractor('928_g');
        $clean   = $this->due($contractor, 100);
        $blocked = $this->due($contractor, 100, ['paid_jod' => 90, 'status' => 'partially_paid']);

        $preview = $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode'           => 'ids',
            'ids'            => [$clean->id, $blocked->id],
            'discount_type'  => 'percent',
            'discount_value' => 50,
            'dry_run'        => true,
        ])->assertOk()->json('items');

        $this->assertSame(2, $preview['matched_count']);
        $this->assertSame(1, $preview['applicable_count']);
        $this->assertSame(50.0, (float) $preview['total_discount_impact_jod']);
        $this->assertSame($blocked->id, $preview['skipped'][0]['due_id']);
    }

    public function test_apply_reports_the_same_counts_the_preview_did(): void
    {
        $this->actingAsAdmin();

        $contractor = $this->contractor('928_g');
        $clean   = $this->due($contractor, 100);
        $blocked = $this->due($contractor, 100, ['paid_jod' => 90, 'status' => 'partially_paid']);

        $payload = [
            'mode'           => 'ids',
            'ids'            => [$clean->id, $blocked->id],
            'discount_type'  => 'percent',
            'discount_value' => 50,
        ];

        $preview = $this->postJson('/api/v1/dashboard/dues/discount/bulk', $payload + ['dry_run' => true])->json('items');
        $applied = $this->postJson('/api/v1/dashboard/dues/discount/bulk', $payload)->assertOk()->json('items');

        $this->assertSame($preview['matched_count'], $applied['matched_count']);
        $this->assertSame($preview['applicable_count'], $applied['applied_count']);
        $this->assertSame('100.00', $blocked->fresh()->amount_jod, 'الذمة المرفوضة تبقى بمبلغها الأصلي');
        $this->assertNull($blocked->fresh()->discount_type, 'ولا يُسجَّل عليها أي خصم');
    }
}
