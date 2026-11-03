<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Services\MembershipCertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * شهادة العضوية لازم تطلع صفحة وحدة مهما كان عدد التخصصات (أقصى عدد فعلي 8).
 */
class MembershipCertificateOnePageTest extends TestCase
{
    use RefreshDatabase;

    private function pageCount(string $pdf): int
    {
        // قاموس الصفحات ما بيتشفّر مع SetProtection (التشفير للسلاسل والـstreams فقط)
        return preg_match_all('#/Type\s*/Page(?![s\w])#', $pdf);
    }

    private function render(int $specialties): string
    {
        Storage::fake('public');

        $fields = [20, 30, 40, 50, 60];
        $specs  = [20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120, 130, 140];
        $list   = [];
        for ($i = 0; $i < $specialties; $i++) {
            $list[] = [
                'field_lk_type'          => $fields[$i % count($fields)],
                'specialization_lk_type' => $specs[$i % count($specs)],
                'classification'         => 'اولى أ',
            ];
        }

        $c = Contractor::create([
            'name'                            => 'شركة الاتحاد العربي للمقاولات العامة والإنشاءات والطرق والتجارة المحدودة',
            'membership_number'               => (1000 + $specialties) . '_g',
            'status'                          => 'active',
            'is_frozen'                       => false,
            'city'                            => 'غزة - الرمال - شارع عمر المختار',
            'classification_decision_number'  => '2026/145',
            'specialties'                     => $list,
        ]);
        $cert = $c->certificateRequests()->create(['type' => 'membership', 'status' => 'approved']);

        $path = app(MembershipCertificatePdfService::class)->generate($cert);
        $pdf  = Storage::disk('public')->get($path);

        if ($dir = env('CERT_PDF_DUMP_DIR')) {
            file_put_contents("{$dir}/cert-{$specialties}.pdf", $pdf);
        }

        return $pdf;
    }

    public function test_fits_on_one_page_for_every_specialty_count_up_to_the_maximum(): void
    {
        foreach ([0, 1, 3, 4, 5, 6, 7, 8] as $n) {
            $this->assertSame(1, $this->pageCount($this->render($n)), "{$n} specialties should fit on one page");
        }
    }
}
