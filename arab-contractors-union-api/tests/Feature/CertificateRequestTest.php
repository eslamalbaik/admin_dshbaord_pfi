<?php

namespace Tests\Feature;

use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '950_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    /** مقاول باكتمال ملف شخصي كامل + عضوية نشطة — يحقق شروط طلب أي نوع شهادة */
    private function createCompliantContractor(array $attrs = []): Contractor
    {
        $contractor = $this->createContractor(array_merge([
            'owner_name'         => 'مالك الشركة',
            'authorized_person'  => 'مفوّض التوقيع',
            'phone'              => '0590000001',
            'address'            => 'رام الله - شارع الإرسال',
            'license_number'     => 'LIC-1',
            'established_date'   => '2010-01-01',
            'capital'            => 10000,
            'legal_form'         => 'شركة مساهمة',
            'registration_date'  => '2010-01-01',
            'company_purposes'   => 'مقاولات إنشائية',
            'city'               => 'رام الله',
            'cr_file'                        => 'docs/cr.pdf',
            'company_register'               => 'docs/reg.pdf',
            'municipal_license'              => 'docs/muni.pdf',
            'bank_dealing_letter'            => 'docs/bank.pdf',
            'articles_of_association'        => 'docs/articles.pdf',
            'internal_bylaws'                => 'docs/bylaws.pdf',
            'lease_or_ownership_contract'    => 'docs/lease.pdf',
            'partners_ids'                   => 'docs/partners.pdf',
            'authorization_letter'           => 'docs/auth.pdf',
            'company_approval_letter'        => 'docs/approval.pdf',
            'full_time_engineer_certificate' => 'docs/engineer.pdf',
            'secretary_contract'             => 'docs/secretary.pdf',
        ], $attrs));

        Membership::create([
            'contractor_id' => $contractor->id,
            'type'          => 'new',
            'status'        => 'active',
            'expires_at'    => now()->addYear(),
        ]);

        return $contractor->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  index
    // ─────────────────────────────────────────────────────────────────────

    public function test_index_returns_expected_structure(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/certificate-requests')
            ->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    'contractor' => ['name', 'membership_number', 'status', 'is_frozen'],
                    'can_request', 'requirement_issues', 'profile_data_complete',
                    'missing_profile_fields', 'requests',
                ],
            ]);
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/v1/contractor/certificate-requests')->assertStatus(401);
    }

    public function test_index_only_lists_own_requests(): void
    {
        $me      = $this->createCompliantContractor();
        $another = $this->createCompliantContractor([
            'membership_number' => '951_g', 'phone' => '0590000099', 'license_number' => 'LIC-2',
        ]);

        CertificateRequest::create(['contractor_id' => $me->id, 'type' => 'good_standing', 'status' => 'pending']);
        CertificateRequest::create(['contractor_id' => $another->id, 'type' => 'good_standing', 'status' => 'pending']);

        Sanctum::actingAs($me, ['*']);

        $this->getJson('/api/v1/contractor/certificate-requests')
            ->assertJsonCount(1, 'items.requests');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  store
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_fails_validation_without_type(): void
    {
        $contractor = $this->createCompliantContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/certificate-requests', [])
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_invalid_type_enum(): void
    {
        $contractor = $this->createCompliantContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'not_a_real_type'])
            ->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_store_fails_when_profile_incomplete(): void
    {
        $contractor = $this->createContractor(); // ناقص البيانات عمداً
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'good_standing']);

        $response->assertStatus(403)->assertJsonPath('error', 'profile_incomplete');
        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_store_creates_request_and_persists_to_database(): void
    {
        $contractor = $this->createCompliantContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/certificate-requests', [
            'type'  => 'good_standing',
            'notes' => 'أحتاجها لتقديم عطاء',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('items.type', 'good_standing')
            ->assertJsonPath('items.status', 'pending')
            ->assertJsonPath('items.contractor_id', $contractor->id);

        $this->assertDatabaseHas('certificate_requests', [
            'contractor_id' => $contractor->id,
            'type'          => 'good_standing',
            'status'        => 'pending',
        ]);

        // re-fetch through API confirms DB state
        $this->getJson('/api/v1/contractor/certificate-requests')
            ->assertJsonCount(1, 'items.requests')
            ->assertJsonPath('items.requests.0.notes', 'أحتاجها لتقديم عطاء');
    }

    public function test_store_with_attachment_persists_file(): void
    {
        $contractor = $this->createCompliantContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->post('/api/v1/contractor/certificate-requests', [
            'type'       => 'experience',
            'attachment' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201);
        $certRequest = CertificateRequest::first();
        Storage::disk('public')->assertExists($certRequest->attachment);
    }

    public function test_store_rejects_duplicate_pending_request_of_same_type(): void
    {
        $contractor = $this->createCompliantContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'good_standing'])->assertStatus(201);
        $response = $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'good_standing']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('certificate_requests', 1);
    }

    public function test_store_membership_certificate_succeeds_with_active_membership_and_no_dues(): void
    {
        $contractor = $this->createCompliantContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership'])
            ->assertStatus(201)
            ->assertJsonPath('items.type', 'membership');
    }

    public function test_store_membership_certificate_fails_without_active_membership(): void
    {
        $contractor = $this->createContractor([
            'owner_name' => 'x', 'authorized_person' => 'x', 'phone' => '0590000002',
            'address' => 'x', 'license_number' => 'x', 'established_date' => '2010-01-01',
            'capital' => 1, 'legal_form' => 'x', 'registration_date' => '2010-01-01',
            'company_purposes' => 'x', 'city' => 'x',
            'cr_file' => 'x', 'company_register' => 'x', 'municipal_license' => 'x',
            'bank_dealing_letter' => 'x', 'articles_of_association' => 'x', 'internal_bylaws' => 'x',
            'lease_or_ownership_contract' => 'x', 'partners_ids' => 'x', 'authorization_letter' => 'x',
            'company_approval_letter' => 'x', 'full_time_engineer_certificate' => 'x', 'secretary_contract' => 'x',
        ]); // profile كامل لكن بدون عضوية نشطة
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership']);

        $response->assertStatus(403)->assertJsonPath('error', 'requirements_pending');
    }

    public function test_store_requires_auth(): void
    {
        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'good_standing'])->assertStatus(401);
    }
}
