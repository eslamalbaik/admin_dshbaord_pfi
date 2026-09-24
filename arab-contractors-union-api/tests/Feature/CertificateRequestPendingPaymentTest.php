<?php

namespace Tests\Feature;

use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-17 #5 — طلب شهادة العضوية يصل اللوحة بانتظار اعتماد دفعة الرسوم.
 *
 * المقاول يدفع من التطبيق فتُسجَّل Payment بحالة pending، ولا يتحرّك paid_jod حتى يعتمدها
 * المحاسب — فكان checkEligibility يرفض الطلب بـ403 (dues_below_threshold) ولا يُنشأ صف
 * إطلاقاً، فتظهر اللوحة فارغة لمن دفع فعلاً. الحلّ يُبدّل التوقيت لا الحدّ: الطلب يُرى
 * ويُراجَع، والموافقة والإصدار موقوفان حتى يتحقّق المال.
 */
class CertificateRequestPendingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
    }

    private function contractorOwingDues(): Contractor
    {
        $contractor = Contractor::create([
            'name'               => 'شركة اختبار للمقاولات',
            'membership_number'  => '950_g',
            'status'             => 'active',
            'is_frozen'          => false,
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
            'accountant_certificate_or_contract' => 'docs/accountant.pdf',
            'secretary_contract'             => 'docs/secretary.pdf',
        ]);

        Membership::create([
            'contractor_id' => $contractor->id,
            'type'          => 'new',
            'status'        => 'active',
            'expires_at'    => now()->addYear(),
        ]);

        // ذمّة السنة الحالية غير مسدَّدة — تُنزل المقاول تحت حدّ الـ95%
        ContractorDue::create([
            'contractor_id' => $contractor->id,
            'year'          => now()->year,
            'description'   => 'رسوم عضوية ' . now()->year,
            'amount_jod'    => 500,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ]);

        return $contractor->fresh();
    }

    private function pendingFeePayment(Contractor $contractor): Payment
    {
        return Payment::create([
            'contractor_id'    => $contractor->id,
            'amount'           => 500,
            'currency'         => 'JOD',
            'type'             => 'membership_fee',
            'status'           => 'pending',
            'method'           => 'bank_transfer',
            'receipt_image'    => 'payment-receipts/r.png',
            'submitted_at'     => now(),
        ]);
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function test_request_is_refused_when_dues_are_short_and_no_payment_was_submitted(): void
    {
        $contractor = $this->contractorOwingDues();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'dues_below_threshold');

        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_request_is_filed_while_the_fee_payment_awaits_confirmation(): void
    {
        $contractor = $this->contractorOwingDues();
        $payment = $this->pendingFeePayment($contractor);

        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership'])
            ->assertCreated()
            ->assertJsonPath('items.awaiting_payment_confirmation', true)
            ->assertJsonPath('items.pending_payment.id', $payment->id);

        $this->assertDatabaseHas('certificate_requests', [
            'contractor_id'      => $contractor->id,
            'status'             => 'pending',
            'pending_payment_id' => $payment->id,
        ]);
    }

    /** الطلب صار ظاهراً للأدمن — وهو جوهر الشكوى: "لا يتم اظهار طلب في لوحة". */
    public function test_the_request_appears_in_the_admin_list(): void
    {
        $contractor = $this->contractorOwingDues();
        $this->pendingFeePayment($contractor);

        Sanctum::actingAs($contractor, ['*']);
        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership'])->assertCreated();

        $this->actingAsAdmin();

        $this->getJson('/api/v1/dashboard/certificate-requests')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('items.0.awaiting_payment_confirmation', true);
    }

    public function test_approve_is_refused_while_the_payment_is_unconfirmed(): void
    {
        $certRequest = $this->fileRequest();

        $this->actingAsAdmin();

        $this->postJson("/api/v1/dashboard/certificate-requests/{$certRequest->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error', 'payment_unconfirmed');

        $this->assertSame('pending', $certRequest->fresh()->status);
    }

    public function test_issue_is_refused_while_the_payment_is_unconfirmed(): void
    {
        $certRequest = $this->fileRequest();

        $this->actingAsAdmin();

        $this->post("/api/v1/dashboard/certificate-requests/{$certRequest->id}/issue", [
            'certificate' => UploadedFile::fake()->create('cert.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'payment_unconfirmed');

        $this->assertNull($certRequest->fresh()->certificate_path);
    }

    public function test_approve_succeeds_once_the_payment_is_confirmed(): void
    {
        $certRequest = $this->fileRequest();
        $certRequest->pendingPayment->update(['status' => 'paid']);

        $this->actingAsAdmin();

        $this->postJson("/api/v1/dashboard/certificate-requests/{$certRequest->id}/approve")->assertOk();

        $this->assertSame('approved', $certRequest->fresh()->status);
    }

    /** عائق غير مالي يبقى مانعاً للتقديم — التسهيل محصور بالحدّ المالي وحده. */
    public function test_a_frozen_contractor_is_still_refused_even_with_a_pending_payment(): void
    {
        $contractor = $this->contractorOwingDues();
        $this->pendingFeePayment($contractor);
        $contractor->update(['is_frozen' => true]);

        Sanctum::actingAs($contractor, ['*']);

        // middleware contractor.active يوقف كل مسارات البوابة للحساب المجمَّد
        $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership'])
            ->assertStatus(403);

        $this->assertDatabaseCount('certificate_requests', 0);
    }

    private function fileRequest(): CertificateRequest
    {
        $contractor = $this->contractorOwingDues();
        $this->pendingFeePayment($contractor);

        Sanctum::actingAs($contractor, ['*']);
        $response = $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership'])->assertCreated();

        return CertificateRequest::findOrFail($response->json('items.id'));
    }
}
