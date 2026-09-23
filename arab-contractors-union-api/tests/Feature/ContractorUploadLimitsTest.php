<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-16 #2/#3 — سقف الرفع وحدّيته.
 *
 * كانت القاعدة max:10240 ثابتة بينما upload_max_filesize على الإنتاج 2M، فأي ملف
 * بينهما يُسقطه PHP قبل التحقق ولا تصدر رسالة حجم إطلاقاً. الاختبارات هنا تثبّت أن
 * السقف صار مشتقاً من ini وأن تجاوزه يُنتج خطأ يسمّي الحقل والحجم.
 */
class ContractorUploadLimitsTest extends TestCase
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

    private function basePayload(array $extra = []): array
    {
        return array_merge([
            'name'                => 'شركة اختبار للمقاولات',
            'membership_number'   => '961_g',
            'commercial_register' => '563961123',
            'governorate_id'      => 1,
            'city_id'             => 1,
            'district'            => 'الرمال',
            'building'            => 'عمارة 3',
            'floor'               => 'الثاني',
        ], $extra);
    }

    public function test_effective_max_never_exceeds_the_php_ini_ceiling(): void
    {
        $iniMaxKb = (int) (self::iniBytes(ini_get('upload_max_filesize')) / 1024);

        $this->assertLessThanOrEqual(
            $iniMaxKb,
            UploadLimits::maxFileKb(),
            'السقف المعلَن تجاوز ما يسمح به PHP — وهو بالضبط الحالة التي تُسقط الملف بلا رسالة حجم.'
        );
    }

    public function test_upload_limits_are_exposed_to_the_forms(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/app/specialties-catalog');
        $response->assertStatus(200);

        $limits = $response->json('items.upload_limits');

        $this->assertNotNull($limits, 'النماذج تقرأ القيود من هنا — غيابها يُعيدها لرقم ثابت يفارق الخادم.');
        $this->assertEquals(UploadLimits::maxFileKb(), $limits['max_file_kb']);
        $this->assertContains('pdf', $limits['allowed_extensions']);
        $this->assertGreaterThan(0, $limits['max_files']);
    }

    public function test_oversize_document_is_rejected_with_a_size_specific_error(): void
    {
        $this->actingAsAdmin();

        $overLimitKb = UploadLimits::maxFileKb() + 512;

        $response = $this->post('/api/v1/contractors', $this->basePayload([
            'cr_file' => UploadedFile::fake()->create('huge.pdf', $overLimitKb, 'application/pdf'),
        ]), ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('cr_file');

        // يجب أن تسمّي الرسالة الحجم — لا أن تكون "يرجى التحقق من المدخلات" العامة
        $message = implode(' ', $response->json('errors.cr_file'));
        $this->assertMatchesRegularExpression('/\d/', $message);
    }

    public function test_document_within_the_limit_is_accepted(): void
    {
        $this->actingAsAdmin();

        $withinLimitKb = max(1, UploadLimits::maxFileKb() - 512);

        $this->post('/api/v1/contractors', $this->basePayload([
            'cr_file' => UploadedFile::fake()->create('ok.pdf', $withinLimitKb, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(201);
    }

    /** نفس قاعدة الامتدادات المعلَنة للواجهة هي المطبَّقة فعلاً. */
    public function test_disallowed_extension_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/v1/contractors', $this->basePayload([
            'cr_file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cr_file');
    }

    private static function iniBytes(string $value): float
    {
        $value  = trim($value);
        $number = (float) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 ** 3,
            'm'     => $number * 1024 ** 2,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}
