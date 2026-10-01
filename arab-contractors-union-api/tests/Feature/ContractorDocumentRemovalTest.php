<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-16 #4 — حذف مستندات المقاول عبر remove_documents[]. قبل ذلك كان الاستبدال
 * هو السبيل الوحيد لإزالة مستند: handleFileUploads() لم تكن تكتب إلا عند وجود ملف جديد.
 */
class ContractorDocumentRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function createContractorWithDocuments(): Contractor
    {
        $crPath      = UploadedFile::fake()->create('cr.pdf', 10, 'application/pdf')->store('contractors/cr', 'public');
        $licensePath = UploadedFile::fake()->create('license.pdf', 10, 'application/pdf')->store('contractors/licenses', 'public');

        return Contractor::create([
            'name'                => 'شركة اختبار للمقاولات',
            'membership_number'   => '960_g',
            'commercial_register' => '563960123',
            'status'              => 'active',
            'is_frozen'           => false,
            'cr_file'             => $crPath,
            'municipal_license'   => $licensePath,
        ]);
    }

    /**
     * name/membership_number/commercial_register تبقى required حتى في التحديث، فنموذج
     * التعديل الحقيقي يُرسلها دائماً — والاختبار يحاكي ذلك بدل إرسال الحقل المعني وحده.
     */
    private function payload(Contractor $contractor, array $extra = []): array
    {
        return array_merge([
            'name'                => $contractor->name,
            'membership_number'   => $contractor->membership_number,
            'commercial_register' => $contractor->commercial_register,
        ], $extra);
    }

    public function test_remove_documents_nulls_the_column_and_deletes_the_file(): void
    {
        $contractor = $this->createContractorWithDocuments();
        $crPath     = $contractor->cr_file;

        Storage::disk('public')->assertExists($crPath);

        $this->actingAsAdmin();

        $this->putJson("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'remove_documents' => ['cr_file'],
        ]))->assertStatus(200);

        $this->assertNull($contractor->fresh()->cr_file);
        Storage::disk('public')->assertMissing($crPath);
    }

    public function test_remove_documents_leaves_other_documents_untouched(): void
    {
        $contractor  = $this->createContractorWithDocuments();
        $licensePath = $contractor->municipal_license;

        $this->actingAsAdmin();

        $this->putJson("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'remove_documents' => ['cr_file'],
        ]))->assertStatus(200);

        $fresh = $contractor->fresh();
        $this->assertNull($fresh->cr_file);
        $this->assertEquals($licensePath, $fresh->municipal_license);
        Storage::disk('public')->assertExists($licensePath);
    }

    /**
     * TASK-17 #2 — id_file كان مقبولاً بالباك (ضمن FILE_FIELDS) وغائباً كلياً عن نموذج
     * التعديل بالواجهة: لا معاينة ولا استبدال ولا حذف. فبالنسبة لهذا المستند وحده لم تكن
     * شكوى «لا يسمح إلا بالاستبدال» دقيقة — لم يكن الاستبدال متاحاً أصلاً.
     */
    public function test_the_id_document_can_be_removed_like_any_other(): void
    {
        $contractor = $this->createContractorWithDocuments();
        $idPath = UploadedFile::fake()->create('id.pdf', 10, 'application/pdf')->store('contractors/id', 'public');
        $contractor->update(['id_file' => $idPath]);

        Storage::disk('public')->assertExists($idPath);

        $this->actingAsAdmin();

        $this->putJson("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'remove_documents' => ['id_file'],
        ]))->assertStatus(200);

        $fresh = $contractor->fresh();
        $this->assertNull($fresh->id_file);
        Storage::disk('public')->assertMissing($idPath);
        // بقية المستندات كما هي — الحذف محصور بما سُمّي صراحةً
        $this->assertNotNull($fresh->cr_file);
        $this->assertNotNull($fresh->municipal_license);
    }

    public function test_remove_documents_can_remove_several_at_once(): void
    {
        $contractor = $this->createContractorWithDocuments();

        $this->actingAsAdmin();

        $this->putJson("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'remove_documents' => ['cr_file', 'municipal_license'],
        ]))->assertStatus(200);

        $fresh = $contractor->fresh();
        $this->assertNull($fresh->cr_file);
        $this->assertNull($fresh->municipal_license);
    }

    /**
     * رفع بديل + علامة حذف لنفس الحقل في الطلب ذاته — الرفع يجب أن يتقدّم،
     * وإلا مَسَح الحذفُ الملفَ الذي رفعه المستخدم للتوّ.
     */
    public function test_uploading_a_replacement_wins_over_a_stale_remove_flag(): void
    {
        $contractor = $this->createContractorWithDocuments();

        $this->actingAsAdmin();

        $this->put("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'cr_file'          => UploadedFile::fake()->create('new-cr.pdf', 10, 'application/pdf'),
            'remove_documents' => ['cr_file'],
        ]), ['Accept' => 'application/json'])->assertStatus(200);

        $fresh = $contractor->fresh();
        $this->assertNotNull($fresh->cr_file);
        Storage::disk('public')->assertExists($fresh->cr_file);
    }

    public function test_remove_documents_rejects_a_field_outside_the_document_whitelist(): void
    {
        $contractor = $this->createContractorWithDocuments();

        $this->actingAsAdmin();

        $this->putJson("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'remove_documents' => ['membership_number'],
        ]))->assertStatus(422);

        // العمود المستهدَف لم يُمَس
        $this->assertEquals('960_g', $contractor->fresh()->membership_number);
    }

    public function test_removing_an_already_empty_document_is_a_no_op(): void
    {
        $contractor = $this->createContractorWithDocuments();

        $this->actingAsAdmin();

        $this->putJson("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'remove_documents' => ['internal_bylaws'],
        ]))->assertStatus(200);

        $this->assertNull($contractor->fresh()->internal_bylaws);
    }

    public function test_update_without_remove_documents_preserves_existing_documents(): void
    {
        $contractor = $this->createContractorWithDocuments();
        $crPath     = $contractor->cr_file;

        $this->actingAsAdmin();

        $this->putJson("/api/v1/contractors/{$contractor->id}", $this->payload($contractor, [
            'notes' => 'تحديث لا يمسّ المستندات',
        ]))->assertStatus(200);

        $this->assertEquals($crPath, $contractor->fresh()->cr_file);
        Storage::disk('public')->assertExists($crPath);
    }
}
