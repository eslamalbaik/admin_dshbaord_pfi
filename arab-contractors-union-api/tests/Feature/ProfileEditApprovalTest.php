<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-17 US11 — تعديل ملف الشركة من التطبيق يصير طلباً يُراجَع.
 *
 * التطبيق يُرسل تعديلاته إلى POST contractor/auth/profile/update، وهو مسار يكتب على
 * contractors فوراً؛ أما طابور الموافقات فخلف مسار آخر لا ينادِه أحد — فكان المقاول يعدّل
 * بياناته ولا يظهر أي طلب باللوحة، وهي الشكوى بحرفها: «تم تعديل ملف الشركة من خلال التطبيق
 * لكن لن يتم عرض الطلب في لوحة رغم التحديث».
 *
 * كل شيء محكوم بمفتاح profile.edits_require_approval، ومطفأً يبقى السلوك سلوك ما قبل هذا
 * التغيير حرفياً — وهذا ما تضمنه أول مجموعة اختبارات أدناه.
 */
class ProfileEditApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
    }

    private function contractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '928_g',
            'phone'             => '0590001122',
            'password'          => Hash::make('Test@1234'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
            'legal_form'        => 'شركة مساهمة',
            'capital'           => '100000',
            'address'           => 'غزة - الرمال',
        ], $attrs));
    }

    private function actingAsContractor(Contractor $contractor): void
    {
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function enableApproval(): void
    {
        config(['profile.edits_require_approval' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  المفتاح مطفأ — السلوك القديم حرفياً
    // ─────────────────────────────────────────────────────────────────────────

    public function test_with_the_flag_off_the_profile_is_written_directly_as_before(): void
    {
        config(['profile.edits_require_approval' => false]);

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'legal_form' => 'شركة عادية',
        ])->assertOk();

        $this->assertSame('شركة عادية', $contractor->fresh()->legal_form);
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  المفتاح مشتعل — الحقول النصية
    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_text_edit_files_a_request_and_leaves_the_contractor_untouched(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'legal_form' => 'شركة عادية',
            'capital'    => '250000',
        ])
            ->assertOk()
            ->assertJsonPath('items.pending_review', true)
            ->assertJsonPath('items.pending_request.proposed_data.legal_form', 'شركة عادية');

        // جوهر الإصلاح: البيانات لم تُكتب، والطلب ظاهر
        $fresh = $contractor->fresh();
        $this->assertSame('شركة مساهمة', $fresh->legal_form);
        $this->assertSame('100000', $fresh->capital);

        $this->assertDatabaseHas('profile_update_requests', [
            'contractor_id' => $contractor->id,
            'status'        => 'pending',
        ]);
    }

    public function test_instant_fields_still_save_immediately(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'trade' => 'مقاولات عامة',
        ])->assertOk();

        // trade مُراجَع، فلا يُكتب — لكن لا يجوز أن يمنع ذلك كتابة الفوري بنفس الطلب
        $this->assertNull($contractor->fresh()->trade);
    }

    /**
     * fcm_token رمز جهاز لا بيانات ملف. تمريره عبر المراجعة يوقف إشعارات هواتف المقاولين
     * بصمت — وهو نوع الفشل الذي كُتب PushChannelWiringTest لاقتناصه.
     */
    public function test_the_device_token_never_becomes_a_request(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->patchJson('/api/v1/contractor/auth/profile', [
            'fcm_token' => 'device-token-abc',
        ])->assertOk();

        $this->assertSame('device-token-abc', $contractor->fresh()->fcm_token);
        $this->assertDatabaseCount('profile_update_requests', 0);
    }

    /** المسار الثاني للكتابة المباشرة — إغلاق الأول وحده يجعله طريق التفادي. */
    public function test_the_patch_route_files_a_request_for_its_reviewed_field(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->patchJson('/api/v1/contractor/auth/profile', [
            'address' => 'غزة - تل الهوا',
        ])->assertOk()->assertJsonPath('items.pending_review', true);

        $this->assertSame('غزة - الرمال', $contractor->fresh()->address);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  المستندات
    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_document_is_staged_and_the_live_document_is_untouched(): void
    {
        $this->enableApproval();

        $existing = UploadedFile::fake()->create('old-cr.pdf', 10, 'application/pdf')->store('contractors/cr', 'public');
        $contractor = $this->contractor(['cr_file' => $existing]);
        $this->actingAsContractor($contractor);

        $this->post('/api/v1/contractor/auth/profile/update', [
            'cr_file' => UploadedFile::fake()->create('new-cr.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('items.pending_review', true);

        // المستند الحالي لم يُلمس، والجديد مُرحَّل بانتظار الموافقة
        $this->assertSame($existing, $contractor->fresh()->cr_file);
        Storage::disk('public')->assertExists($existing);

        $staged = ProfileUpdateRequest::first()->proposed_files;
        $this->assertArrayHasKey('cr_file', $staged);
        Storage::disk('public')->assertExists($staged['cr_file']);
    }

    public function test_approval_moves_the_staged_document_and_deletes_the_superseded_one(): void
    {
        $this->enableApproval();

        $existing = UploadedFile::fake()->create('old-cr.pdf', 10, 'application/pdf')->store('contractors/cr', 'public');
        $contractor = $this->contractor(['cr_file' => $existing]);
        $this->actingAsContractor($contractor);

        $this->post('/api/v1/contractor/auth/profile/update', [
            'cr_file'    => UploadedFile::fake()->create('new-cr.pdf', 10, 'application/pdf'),
            'legal_form' => 'شركة عادية',
        ], ['Accept' => 'application/json'])->assertOk();

        $requestId = ProfileUpdateRequest::first()->id;

        $this->actingAsAdmin();
        $this->postJson("/api/v1/dashboard/profile-update-requests/{$requestId}/approve")->assertOk();

        $fresh = $contractor->fresh();

        $this->assertSame('شركة عادية', $fresh->legal_form);
        $this->assertStringStartsWith('contractors/cr/', $fresh->cr_file);
        $this->assertNotSame($existing, $fresh->cr_file);
        Storage::disk('public')->assertExists($fresh->cr_file);
        Storage::disk('public')->assertMissing($existing);
    }

    public function test_rejection_leaves_everything_untouched_and_drops_the_staged_file(): void
    {
        $this->enableApproval();

        $existing = UploadedFile::fake()->create('old-cr.pdf', 10, 'application/pdf')->store('contractors/cr', 'public');
        $contractor = $this->contractor(['cr_file' => $existing]);
        $this->actingAsContractor($contractor);

        $this->post('/api/v1/contractor/auth/profile/update', [
            'cr_file'    => UploadedFile::fake()->create('new-cr.pdf', 10, 'application/pdf'),
            'legal_form' => 'شركة عادية',
        ], ['Accept' => 'application/json'])->assertOk();

        $profileRequest = ProfileUpdateRequest::first();
        $staged = $profileRequest->proposed_files['cr_file'];

        $this->actingAsAdmin();
        $this->postJson("/api/v1/dashboard/profile-update-requests/{$profileRequest->id}/reject", [
            'reject_reason' => 'المستند غير واضح',
        ])->assertOk();

        $fresh = $contractor->fresh();
        $this->assertSame('شركة مساهمة', $fresh->legal_form);
        $this->assertSame($existing, $fresh->cr_file);
        Storage::disk('public')->assertExists($existing);
        Storage::disk('public')->assertMissing($staged);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  الإلغاء بطلب أحدث
    // ─────────────────────────────────────────────────────────────────────────

    public function test_a_second_edit_supersedes_the_pending_one(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->post('/api/v1/contractor/auth/profile/update', [
            'cr_file'    => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'),
            'legal_form' => 'خطأ مطبعي',
        ], ['Accept' => 'application/json'])->assertOk();

        $first = ProfileUpdateRequest::first();
        $firstStaged = $first->proposed_files['cr_file'];

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'legal_form' => 'شركة عادية',
        ])->assertOk();

        // الطلب الأول أُلغي وحُذفت ملفاته — لا رفض للتعديل الثاني، فالمقاول يصحّح خطأه فوراً
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->superseded_at);
        Storage::disk('public')->assertMissing($firstStaged);

        $this->assertSame(1, ProfileUpdateRequest::where('status', 'pending')->count());
        $this->assertSame('شركة عادية', ProfileUpdateRequest::where('status', 'pending')->first()->proposed_data['legal_form']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  عرض الطلب المعلّق للتطبيق ولّلوحة
    // ─────────────────────────────────────────────────────────────────────────

    public function test_the_profile_endpoint_exposes_the_pending_request(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['legal_form' => 'شركة عادية'])->assertOk();

        $this->getJson('/api/v1/contractor/auth/profile')
            ->assertOk()
            ->assertJsonPath('items.pending_profile_update.proposed_data.legal_form', 'شركة عادية');
    }

    public function test_the_admin_list_shows_current_versus_proposed_for_a_widened_field(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['legal_form' => 'شركة عادية'])->assertOk();

        $this->actingAsAdmin();

        // current_data كان يُرجع null لكل ما هو خارج ALLOWED_FIELDS الخمسة القديمة —
        // فكان المراجِع يوافق على قيمة مقترحة بلا ما يقارنها به.
        $this->getJson('/api/v1/dashboard/profile-update-requests')
            ->assertOk()
            ->assertJsonPath('items.0.current_data.legal_form', 'شركة مساهمة')
            ->assertJsonPath('items.0.proposed_data.legal_form', 'شركة عادية');
    }

    public function test_the_admin_list_exposes_the_staged_document_for_review(): void
    {
        $this->enableApproval();

        $existing = UploadedFile::fake()->create('old-cr.pdf', 10, 'application/pdf')->store('contractors/cr', 'public');
        $contractor = $this->contractor(['cr_file' => $existing]);
        $this->actingAsContractor($contractor);

        $this->post('/api/v1/contractor/auth/profile/update', [
            'cr_file' => UploadedFile::fake()->create('new-cr.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->actingAsAdmin();

        $this->getJson('/api/v1/dashboard/profile-update-requests')
            ->assertOk()
            ->assertJsonPath('items.0.proposed_documents.0.field', 'cr_file')
            ->assertJsonPath('items.0.proposed_documents.0.label', 'السجل التجاري')
            ->assertJsonStructure(['items' => [['proposed_documents' => [['proposed_url', 'current_url']]]]]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  الحقول المقفلة تبقى مقفلة — الطابور ليس طريقاً خلفياً إليها
    // ─────────────────────────────────────────────────────────────────────────

    public function test_fee_fields_cannot_reach_the_queue_either(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor(['classification' => 'اولى أ']);
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'classification' => 'خامسة',
            'legal_form'     => 'شركة عادية',
        ])->assertOk();

        $pending = ProfileUpdateRequest::where('status', 'pending')->first();

        $this->assertArrayNotHasKey('classification', $pending->proposed_data);
        $this->assertSame('اولى أ', $contractor->fresh()->classification);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  تنظيف الملفات المرحَّلة المهجورة
    // ─────────────────────────────────────────────────────────────────────────

    public function test_prune_deletes_orphaned_staged_files_and_spares_pending_ones(): void
    {
        $this->enableApproval();

        $contractor = $this->contractor();
        $this->actingAsContractor($contractor);

        $this->post('/api/v1/contractor/auth/profile/update', [
            'cr_file' => UploadedFile::fake()->create('pending.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $pendingStaged = ProfileUpdateRequest::first()->proposed_files['cr_file'];

        // ملف مهجور: موجود بمجلّد الترحيل ولا يعود لأي طلب معلّق
        $orphan = 'contractors/profile-update/staged/' . $contractor->id . '/orphan.pdf';
        Storage::disk('public')->put($orphan, 'x');

        config(['profile.staged_files_retention_days' => 0]);

        $this->artisan('profile-requests:prune-staged')->assertSuccessful();

        Storage::disk('public')->assertMissing($orphan);
        Storage::disk('public')->assertExists($pendingStaged);
    }
}
