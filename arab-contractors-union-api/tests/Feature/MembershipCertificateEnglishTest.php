<?php

namespace Tests\Feature;

use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Models\User;
use App\Services\MembershipCertificatePdfService;
use App\Support\ContractorLookups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * النسخة الإنجليزية من شهادة العضوية: نفس الطلب ونفس الرقم التسلسلي، ملف تاني (MC-…-en.pdf)،
 * بتطلع بس لما يكون للمقاول اسم إنجليزي، وبتضل صفحة وحدة.
 */
class MembershipCertificateEnglishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        ContractorLookups::clearCache();
    }

    private function contractor(array $attrs = [], int $specialties = 2): Contractor
    {
        $fields = [20, 30, 40, 50, 60];
        $specs  = [20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140];
        $grades = ['اولى أ', 'اولى ب', 'ثانية', 'ثالثة'];
        $list   = [];
        for ($i = 0; $i < $specialties; $i++) {
            $list[] = [
                'field_lk_type'          => $fields[$i % count($fields)],
                'specialization_lk_type' => $specs[$i % count($specs)],
                'classification'         => $grades[$i % count($grades)],
            ];
        }

        return Contractor::create($attrs + [
            'name'                           => 'شركة الاتحاد العربي للمقاولات',
            'name_en'                        => 'Arab Union Contracting Co.',
            'address_en'                     => 'Gaza - Al-Rimal',
            'membership_number'              => '901_g',
            'status'                         => 'active',
            'is_frozen'                      => false,
            'city'                           => 'غزة',
            'classification_decision_number' => '2026/145',
            'classification_decision_date'   => '2024-03-01',
            'specialties'                    => $list,
        ]);
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('#/Type\s*/Page(?![s\w])#', $pdf);
    }

    private function relative(string $url): string
    {
        $parts = parse_url($url);

        return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    public function test_english_certificate_fits_one_page_for_every_specialty_count(): void
    {
        foreach ([0, 1, 4, 8] as $n) {
            $c = $this->contractor(['membership_number' => (1000 + $n) . '_g', 'name_en' => 'Arab Union for General Contracting, Construction, Roads and Trading Ltd.'], $n);
            $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued']);
            $cert->forceFill(['issued_at' => now(), 'valid_until' => now()->endOfYear()->toDateString()])->save();

            $path = app(MembershipCertificatePdfService::class)->generateEnglish($cert->fresh());
            $pdf  = Storage::disk('public')->get($path);

            if ($dir = env('CERT_PDF_DUMP_DIR')) {
                file_put_contents("{$dir}/cert-en-{$n}.pdf", $pdf);
            }

            $this->assertStringEndsWith('-en.pdf', $path);
            $this->assertSame(1, $this->pageCount($pdf), "{$n} specialties should fit on one page");
        }
    }

    public function test_english_rows_use_translations_and_report_missing_ones(): void
    {
        $result = ContractorLookups::englishCertificateRows([
            ['field_lk_type' => 20, 'specialization_lk_type' => 30, 'classification' => 'اولى أ'],
            ['field_lk_type' => 30, 'specialization_lk_type' => 999, 'classification' => 'ثانية'],
        ]);

        $this->assertSame(['Roads', 'Asphalt Mixes', 'First A'], $result['rows'][0]);
        $this->assertSame(['Buildings', 'غير محدد', 'Second'], $result['rows'][1]);
        $this->assertSame(['غير محدد'], $result['missing']);
    }

    public function test_admin_issue_generates_both_languages_with_one_serial(): void
    {
        $this->actingAsAdmin();
        $c = $this->contractor();

        $res = $this->postJson('/api/v1/dashboard/certificate-requests/issue-membership', [
            'contractor_id' => $c->id,
            'address_en'    => 'Gaza City',
        ])->assertCreated();

        $cert = CertificateRequest::findOrFail($res->json('items.id'));
        $this->assertNotNull($cert->certificate_path);
        $this->assertSame("certificates/membership/MC-" . str_pad((string) $cert->id, 6, '0', STR_PAD_LEFT) . '-en.pdf', $cert->certificate_path_en);
        Storage::disk('public')->assertExists($cert->certificate_path_en);
        $this->assertSame('Gaza City', $cert->certificate_address_en);
        $this->assertTrue($res->json('items.english_available'));
        $this->assertNotNull($res->json('items.certificate_url_en'));
        $this->assertNotNull($cert->serial_number);

        // رابط اللوحة (track=0) بيفتح الملف بدون ما يحسبها فتح على المقاول
        $this->get($this->relative($res->json('items.certificate_url_en')))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(0, (int) $cert->fresh()->views_count);
    }

    public function test_include_english_without_english_name_is_rejected(): void
    {
        $this->actingAsAdmin();
        $c = $this->contractor(['name_en' => null]);

        $this->postJson('/api/v1/dashboard/certificate-requests/issue-membership', [
            'contractor_id'   => $c->id,
            'include_english' => true,
        ])->assertStatus(422)->assertJsonPath('error', 'english_name_missing');

        $this->assertSame(0, CertificateRequest::count());
    }

    public function test_without_english_name_only_arabic_is_issued(): void
    {
        $this->actingAsAdmin();
        $c = $this->contractor(['name_en' => null]);

        $res = $this->postJson('/api/v1/dashboard/certificate-requests/issue-membership', ['contractor_id' => $c->id])
            ->assertCreated();

        $this->assertNull(CertificateRequest::find($res->json('items.id'))->certificate_path_en);
        $this->assertFalse($res->json('items.english_available'));
        $this->assertNull($res->json('items.certificate_url_en'));
    }

    public function test_include_english_false_skips_the_english_copy(): void
    {
        $this->actingAsAdmin();
        $c = $this->contractor();

        $res = $this->postJson('/api/v1/dashboard/certificate-requests/issue-membership', [
            'contractor_id'   => $c->id,
            'include_english' => false,
        ])->assertCreated();

        $this->assertNull(CertificateRequest::find($res->json('items.id'))->certificate_path_en);
    }

    public function test_contractor_gets_english_link_and_it_is_generated_on_first_open(): void
    {
        // شهادة صدرت قبل ما ينعبّى الاسم الإنجليزي
        $c = $this->contractor(['name_en' => null]);
        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued']);
        $path = app(MembershipCertificatePdfService::class)->generate($cert);
        $cert->forceFill(['certificate_path' => $path, 'issued_at' => now()])->save();

        Sanctum::actingAs($c, ['*']);
        $this->getJson('/api/v1/contractor/certificates/status')
            ->assertOk()
            ->assertJsonPath('items.membership.latest_issued.certificate_url_en', null);

        // الأدمن ضاف الاسم الإنجليزي → التطبيق بياخد رابط، وأول فتح بيولّد الملف
        $c->update(['name_en' => 'Late English Name Co.']);
        $url = $this->getJson('/api/v1/contractor/certificates/status')
            ->assertOk()
            ->json('items.membership.latest_issued.certificate_url_en');
        $this->assertNotNull($url);

        $this->get($this->relative($url))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $cert->refresh();
        $this->assertNotNull($cert->certificate_path_en);
        Storage::disk('public')->assertExists($cert->certificate_path_en);
        $this->assertSame(1, (int) $cert->views_count);
    }

    public function test_changing_the_english_name_discards_the_old_english_file(): void
    {
        $this->actingAsAdmin();
        $c = $this->contractor();
        $res = $this->postJson('/api/v1/dashboard/certificate-requests/issue-membership', ['contractor_id' => $c->id])->assertCreated();
        $cert = CertificateRequest::findOrFail($res->json('items.id'));
        $old = $cert->certificate_path_en;
        $this->assertNotNull($old);

        $c->update(['name_en' => 'Renamed Contracting Co.']);

        $this->assertNull($cert->fresh()->certificate_path_en);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_english_link_is_unavailable_for_revoked_certificate(): void
    {
        $c = $this->contractor();
        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued']);
        $path = app(MembershipCertificatePdfService::class)->generate($cert);
        $cert->forceFill(['certificate_path' => $path, 'issued_at' => now(), 'revoked_at' => now()])->save();

        $url = $cert->fresh()->englishCertificateUrl();
        $this->get($this->relative($url))->assertNotFound();
    }

    public function test_verification_returns_english_company_name(): void
    {
        $c = $this->contractor();
        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued']);
        $cert->forceFill(['issued_at' => now(), 'valid_until' => now()->addMonth()->toDateString()])->save();
        $serial = $cert->ensureSerialNumber();

        $this->getJson('/api/v1/certificates/check?serial=' . $serial)
            ->assertOk()
            ->assertJsonPath('items.contractor_name_en', 'Arab Union Contracting Co.');
    }

    public function test_verify_page_gets_view_and_download_links_for_a_valid_certificate(): void
    {
        $c = $this->contractor();
        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued']);
        $path = app(MembershipCertificatePdfService::class)->generate($cert);
        $cert->forceFill(['certificate_path' => $path, 'issued_at' => now(), 'valid_until' => now()->addMonth()->toDateString()])->save();
        $serial = $cert->ensureSerialNumber();

        $res = $this->getJson('/api/v1/certificates/check?serial=' . $serial)->assertOk();
        foreach (['certificate_url', 'certificate_url_en', 'download_url', 'download_url_en'] as $key) {
            $this->assertNotNull($res->json("items.{$key}"), $key);
        }

        // العرض: inline وبيسمح بالـiframe من الواجهة، وما بينحسب فتح على المقاول
        $view = $this->get($this->relative($res->json('items.certificate_url')))->assertOk();
        $this->assertStringStartsWith('inline', $view->headers->get('Content-Disposition'));
        $this->assertStringContainsString('frame-ancestors', $view->headers->get('Content-Security-Policy'));
        $this->assertFalse($view->headers->has('X-Frame-Options'));
        $this->assertSame(0, (int) $cert->fresh()->views_count);

        // التحميل: attachment، والنسخة الإنجليزية بتتولّد عند الطلب
        $dl = $this->get($this->relative($res->json('items.download_url_en')))->assertOk();
        $this->assertStringStartsWith('attachment', $dl->headers->get('Content-Disposition'));
        $this->assertStringContainsString('-en.pdf', $dl->headers->get('Content-Disposition'));

        // باقي الردود لسا ممنوع تنعرض بإطار
        $this->getJson('/api/v1/certificates/check?serial=' . $serial)->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_verify_page_gets_no_links_for_a_revoked_certificate(): void
    {
        $c = $this->contractor();
        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'issued']);
        $path = app(MembershipCertificatePdfService::class)->generate($cert);
        $cert->forceFill(['certificate_path' => $path, 'issued_at' => now(), 'revoked_at' => now()])->save();
        $serial = $cert->ensureSerialNumber();

        $this->getJson('/api/v1/certificates/check?serial=' . $serial)
            ->assertOk()
            ->assertJsonPath('items.certificate_url', null)
            ->assertJsonPath('items.download_url', null);
    }
}
