<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POST /contractor/auth/profile/update — مستندات الشركة من شاشة تعديل الملف بالتطبيق.
 *
 * كانت القاعدة هنا mimes:pdf,jpg,jpeg,png|max:10240 ثابتة، فيُرفض ملف Word يقبله
 * الأدمن، وتظهر الرسالة "يجب أن يكون cr file ملفاً من نوع..." باسم الحقل الخام.
 * المطلوب: pdf/doc/docx/jpg/jpeg/png وحتى 12 ميجابايت، كلوحة الأدمن تماماً.
 */
class ContractorProfileUploadRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsContractor(): Contractor
    {
        $contractor = Contractor::create([
            'name'              => 'شركة اختبار',
            'membership_number' => '951_g',
            'phone'             => '0590001133',
            'password'          => Hash::make('Test@1234'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ]);

        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);

        return $contractor;
    }

    private function upload(array $files)
    {
        return $this->post('/api/v1/contractor/auth/profile/update', $files, ['Accept' => 'application/json']);
    }

    public function test_requested_extensions_and_size_are_the_app_rule(): void
    {
        $this->assertSame(['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], UploadLimits::ALLOWED_EXTENSIONS);
        $this->assertSame(12288, UploadLimits::CONFIGURED_MAX_KB);
    }

    public function test_word_documents_are_accepted(): void
    {
        $contractor = $this->actingAsContractor();

        $this->upload([
            'cr_file'          => UploadedFile::fake()->create('register.docx', 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'company_register' => UploadedFile::fake()->create('extract.doc', 200, 'application/msword'),
        ])->assertStatus(200);

        $contractor->refresh();
        $this->assertNotEmpty($contractor->cr_file);
        $this->assertNotEmpty($contractor->company_register);
    }

    public function test_pdf_and_images_are_still_accepted(): void
    {
        $this->actingAsContractor();

        $this->upload([
            'cr_file'              => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf'),
            'id_file'              => UploadedFile::fake()->image('id.jpg'),
            'authorized_signature' => UploadedFile::fake()->image('sig.png'),
        ])->assertStatus(200);
    }

    public function test_file_over_ten_mb_but_within_the_limit_is_accepted(): void
    {
        if (UploadLimits::maxFileKb() < 11264) {
            $this->markTestSkipped('upload_max_filesize في بيئة الاختبار أقل من 11M.');
        }

        $this->actingAsContractor();

        $this->upload([
            'cr_file' => UploadedFile::fake()->create('big.pdf', 11264, 'application/pdf'),
        ])->assertStatus(200);
    }

    public function test_oversize_file_is_rejected_with_arabic_label_and_mb_limit(): void
    {
        $this->actingAsContractor();

        $response = $this->upload([
            'cr_file' => UploadedFile::fake()->create('huge.pdf', UploadLimits::maxFileKb() + 512, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('cr_file');
        $message = implode(' ', $response->json('errors.cr_file'));
        $this->assertStringContainsString('السجل التجاري', $message);
        $this->assertStringContainsString('ميجابايت', $message);
    }

    public function test_disallowed_extension_message_names_document_and_formats(): void
    {
        $this->actingAsContractor();

        $response = $this->upload([
            'cr_file' => UploadedFile::fake()->create('sheet.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('cr_file');
        $message = implode(' ', $response->json('errors.cr_file'));
        $this->assertStringContainsString('السجل التجاري', $message);
        $this->assertStringContainsString('docx', $message);
        $this->assertStringNotContainsString('cr file', $message);
    }

    /** ملف حقيقي على القرص (لا fake) حتى يمر عبر fileinfo فعلاً كما في الإنتاج. */
    private function realFile(string $name, string $bytes, string $clientMime = 'application/octet-stream'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $clientMime, null, true);
    }

    private function minimalPdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    public function test_bank_pdf_with_bytes_before_header_is_accepted_and_saved_as_pdf(): void
    {
        $contractor = $this->actingAsContractor();

        // كشف حساب من تطبيق بنك: بايتات قبل %PDF فيقرؤه fileinfo octet-stream، و mimes كانت ترفضه.
        $file = $this->realFile('كشف_الحساب_0451153.pdf', "\r\n\x00\x00JUNK" . $this->minimalPdf(), 'application/pdf');

        $this->upload([
            'articles_of_association' => $file,
            'bank_dealing_letter'     => $this->realFile('كشف_الحساب_0451153.pdf', "\r\n\x00\x00JUNK" . $this->minimalPdf(), 'application/pdf'),
        ])->assertStatus(200);

        $contractor->refresh();
        $this->assertStringEndsWith('.pdf', $contractor->articles_of_association);
        $this->assertStringEndsWith('.pdf', $contractor->bank_dealing_letter);
        Storage::disk('public')->assertExists($contractor->articles_of_association);
    }

    public function test_docx_detected_as_zip_is_accepted(): void
    {
        $contractor = $this->actingAsContractor();

        $zipPath = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', '<w:document/>');
        $zip->close();

        $this->upload([
            'internal_bylaws' => $this->realFile('bylaws.docx', file_get_contents($zipPath)),
        ])->assertStatus(200);

        $this->assertStringEndsWith('.docx', $contractor->refresh()->internal_bylaws);
    }

    public function test_empty_file_gets_a_clear_message(): void
    {
        $this->actingAsContractor();

        $response = $this->upload(['articles_of_association' => $this->realFile('x.pdf', '')]);

        $response->assertStatus(422);
        $this->assertStringContainsString('فارغ', implode(' ', $response->json('errors.articles_of_association')));
    }

    public function test_real_xlsx_is_rejected_and_message_names_the_uploaded_type(): void
    {
        $this->actingAsContractor();

        $zipPath = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook/>');
        $zip->close();

        $response = $this->upload(['articles_of_association' => $this->realFile('statement.xlsx', file_get_contents($zipPath))]);

        $response->assertStatus(422);
        $message = implode(' ', $response->json('errors.articles_of_association'));
        $this->assertStringContainsString('عقد تأسيس الشركة', $message);
        $this->assertStringContainsString('الملف المرفوع: xlsx', $message);
    }

    public function test_php_disguised_as_pdf_is_rejected(): void
    {
        $this->actingAsContractor();

        $this->upload(['articles_of_association' => $this->realFile('shell.php', "<?php echo 1; %PDF-1.4")])
            ->assertStatus(422);
    }
}
