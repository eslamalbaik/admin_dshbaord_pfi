<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-17 — المقاول لا يعدّل مُدخَلي احتساب الرسوم من بوابته.
 *
 * كان UpdateFullProfileRequest يقبل classification و specialties، و updateFullProfile يكتبهما
 * مباشرة على contractors. وهما ما يقرأه MembershipFeeCalculator نفسه (عبر
 * $contractor->specialties) وما يُبنى عليه توليد الشهادات — فكان المقاول يستطيع من التطبيق
 * تخفيض تصنيفه فيُخفّض الرسم المحتسَب عليه، وتغيير الدرجة المطبوعة على شهادته، بلا مراجعة
 * ولا أثر يُرجَع إليه.
 *
 * التعديل صلاحية إدارية فقط: لوحة الأدمن (ContractorController)، أو طابور طلبات التعديل
 * بعد إنجاز US11.
 */
class ContractorFeeFieldLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function contractor(): Contractor
    {
        return Contractor::create([
            'name'               => 'شركة اختبار للمقاولات',
            'membership_number'  => '928_g',
            'status'             => 'active',
            'is_frozen'          => false,
            'phone'              => '0590000001',
            'password'           => \Illuminate\Support\Facades\Hash::make('Test@1234'),
            'phone_verified_at'  => now(),
            'classification'     => 'اولى أ',
            'specialties'        => [
                ['field_lk_type' => 20, 'specialization_lk_type' => 20, 'classification' => 'اولى أ'],
            ],
        ]);
    }

    /**
     * التفعيل على الغاردين معاً: 'sanctum' لتجاوز middleware الـauth، و'contractor' لأن
     * الكونترولر و UpdateFullProfileRequest يقرآن $request->user('contractor') تحديداً.
     * نفس نمط ContractorProfileAddressTest.
     */
    private function actingAsContractor(Contractor $contractor): void
    {
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
    }

    public function test_a_contractor_cannot_downgrade_their_own_classification(): void
    {
        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'classification' => 'خامسة',
            'capital'        => '250000',
        ])->assertOk();

        $fresh = $contractor->fresh();

        // التصنيف كما هو — والحقل المشروع بنفس الطلب حُفظ فعلاً، فالرفض محصور بما يجب حصره
        $this->assertSame('اولى أ', $fresh->classification);
        $this->assertSame('250000', $fresh->capital);
    }

    public function test_a_contractor_cannot_rewrite_their_own_specialties(): void
    {
        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'specialties' => json_encode([
                ['field_lk_type' => 60, 'specialization_lk_type' => 200, 'classification' => 'خامسة'],
            ]),
        ])->assertOk();

        $specialties = $contractor->fresh()->specialties;

        $this->assertCount(1, $specialties);
        $this->assertSame(20, $specialties[0]['field_lk_type']);
        $this->assertSame('اولى أ', $specialties[0]['classification']);
    }

    /** الرفض صامت عن قصد: نموذج التطبيق يرسل حقوله كاملة، و422 كان سيُعطّل كل حفظ ملف. */
    public function test_sending_a_locked_field_does_not_fail_the_whole_save(): void
    {
        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'classification' => 'خامسة',
            'specialties'    => json_encode([['field_lk_type' => 60, 'specialization_lk_type' => 200, 'classification' => 'خامسة']]),
            'owner_name'     => 'مالك جديد',
            'legal_form'     => 'شركة عادية',
        ])->assertOk();

        $fresh = $contractor->fresh();
        $this->assertSame('مالك جديد', $fresh->owner_name);
        $this->assertSame('شركة عادية', $fresh->legal_form);
        $this->assertSame('اولى أ', $fresh->classification);
    }

    /** إرسال نفس القيمة المخزَّنة ليس محاولة تعديل — لا يجوز أن يُسجَّل كمحاولة. */
    public function test_resending_the_unchanged_value_is_not_treated_as_an_attempt(): void
    {
        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'classification' => 'اولى أ',
        ])->assertOk();

        $this->assertSame('اولى أ', $contractor->fresh()->classification);
    }

    /** المسار الإداري يبقى قادراً — الإغلاق يخصّ بوابة المقاول وحدها. */
    public function test_the_admin_route_can_still_set_the_classification(): void
    {
        $contractor = $this->contractor();

        Sanctum::actingAs(\App\Models\User::factory()->create(['role' => 'admin']), ['*']);

        $this->putJson("/api/v1/contractors/{$contractor->id}", [
            'name'                => $contractor->name,
            'membership_number'   => $contractor->membership_number,
            'commercial_register' => '563960123',
            'classification'      => 'ثانية',
        ])->assertOk();

        $this->assertSame('ثانية', $contractor->fresh()->classification);
    }
}
