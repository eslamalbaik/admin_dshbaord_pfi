<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\GradeFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * سجل المحددات الهامة: تعديل رسوم العضوية وتعديل إصدار شهادة العضوية بيتسجّلوا
 * كأحداث حرجة مع القيم قبل/بعد، والعمليات الطبيعية ما بتنعلّم.
 */
class CriticalActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function contractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'                           => 'شركة اختبار للمقاولات',
            'membership_number'              => '950_g',
            'status'                         => 'active',
            'is_frozen'                      => false,
            'city'                           => 'خانيونس',
            'classification_decision_number' => '04/2022',
            'classification_decision_date'   => '2022-04-01',
        ], $attrs));
    }

    public function test_grade_fee_update_records_before_after_and_reason(): void
    {
        $fee = GradeFee::ordered()->first() ?? GradeFee::create([
            'grade_code' => 'اولى أ', 'grade_label' => 'أولى أ', 'sort_order' => 1,
            'registration_fee_jod' => 500, 'annual_fee_jod' => 300,
        ]);
        $oldAnnual = (float) $fee->annual_fee_jod;

        $this->putJson("/api/v1/dashboard/grade-fees/{$fee->id}", [
            'grade_label'    => $fee->grade_label,
            'annual_fee_jod' => $oldAnnual + 50,
            'reason'         => 'قرار مجلس الإدارة',
        ])->assertOk();

        $log = ActivityLog::where('action', 'grade_fee.updated')->sole();
        $this->assertTrue($log->is_critical);
        // مسمّى الدرجة ما تغيّر فما بيظهر بقائمة التغييرات
        $this->assertSame(['annual_fee_jod'], array_keys($log->meta['after']));
        $this->assertEquals($oldAnnual, $log->meta['before']['annual_fee_jod']);
        $this->assertEquals($oldAnnual + 50, $log->meta['after']['annual_fee_jod']);
        $this->assertSame('قرار مجلس الإدارة', $log->meta['reason']);
    }

    public function test_due_discount_is_critical_with_amounts(): void
    {
        $due = ContractorDue::create([
            'contractor_id' => $this->contractor()->id, 'year' => 2026, 'description' => 'رسوم عضوية 2026',
            'amount_jod' => 200, 'paid_jod' => 0, 'status' => 'unpaid', 'source' => 'manual',
        ]);

        $this->postJson("/api/v1/dashboard/dues/{$due->id}/discount", [
            'discount_type' => 'percent', 'discount_value' => 25, 'discount_reason' => 'حالة إنسانية',
        ])->assertOk();

        $log = ActivityLog::where('action', 'due.discount_applied')->sole();
        $this->assertTrue($log->is_critical);
        $this->assertEquals(200, $log->meta['before']['amount_jod']);
        $this->assertEquals(150, $log->meta['after']['amount_jod']);
        $this->assertSame('حالة إنسانية', $log->meta['reason']);
        $this->assertSame('950_g', $log->meta['membership_number']);
    }

    public function test_admin_issue_with_record_data_is_not_critical(): void
    {
        $contractor = $this->contractor();

        $this->postJson('/api/v1/dashboard/certificate-requests/issue-membership', [
            'contractor_id'   => $contractor->id,
            'address'         => 'خانيونس',
            'decision_number' => '04/2022',
            'decision_date'   => '2022-04-01',
        ])->assertStatus(201);

        $this->assertFalse(ActivityLog::where('action', 'certificate.admin_issued_membership')->sole()->is_critical);
    }

    public function test_admin_issue_with_overridden_data_is_critical(): void
    {
        $contractor = $this->contractor();

        $this->postJson('/api/v1/dashboard/certificate-requests/issue-membership', [
            'contractor_id'   => $contractor->id,
            'address'         => 'خانيونس',
            'decision_number' => '09/2025',
            'decision_date'   => '2022-04-01',
            'notes'           => 'بطلب من الأمين العام',
        ])->assertStatus(201);

        $log = ActivityLog::where('action', 'certificate.admin_issued_membership')->sole();
        $this->assertTrue($log->is_critical);
        $this->assertSame(['decision_number' => '04/2022'], $log->meta['before']);
        $this->assertSame(['decision_number' => '09/2025'], $log->meta['after']);
        $this->assertSame('بطلب من الأمين العام', $log->meta['reason']);
    }

    public function test_regenerate_is_critical(): void
    {
        $contractor = $this->contractor();
        $cert = $contractor->certificateRequests()->create([
            'type' => 'membership', 'status' => 'issued', 'issued_at' => now()->subMonth(),
            'certificate_path' => 'certificates/membership/old.pdf',
        ]);

        $this->postJson("/api/v1/dashboard/certificate-requests/{$cert->id}/regenerate", [
            'address' => 'رفح', 'reason' => 'تصحيح العنوان',
        ])->assertOk();

        $log = ActivityLog::where('action', 'certificate.regenerated')->sole();
        $this->assertTrue($log->is_critical);
        $this->assertSame('خانيونس', $log->meta['before']['address']);
        $this->assertSame('رفح', $log->meta['after']['address']);
        $this->assertArrayHasKey('issued_at', $log->meta['after']);
    }

    public function test_reupload_over_issued_certificate_is_critical_first_upload_is_not(): void
    {
        $contractor = $this->contractor();
        $cert = $contractor->certificateRequests()->create(['type' => 'membership', 'status' => 'approved']);

        $upload = fn () => $this->post("/api/v1/dashboard/certificate-requests/{$cert->id}/issue", [
            'certificate' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $upload();
        $upload();

        $logs = ActivityLog::where('action', 'certificate.issued')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertFalse($logs[0]->is_critical);
        $this->assertTrue($logs[1]->is_critical);
        $this->assertArrayHasKey('certificate_file', $logs[1]->meta['before']);
    }

    public function test_listing_filters_critical_and_exposes_changes(): void
    {
        $fee = GradeFee::ordered()->first() ?? GradeFee::create([
            'grade_code' => 'اولى أ', 'grade_label' => 'أولى أ', 'sort_order' => 1,
            'registration_fee_jod' => 500, 'annual_fee_jod' => 300,
        ]);
        $this->putJson("/api/v1/dashboard/grade-fees/{$fee->id}", ['registration_fee_jod' => 999])->assertOk();
        \App\Services\AuditLogService::record(User::first(), 'page.created');

        $this->getJson('/api/v1/dashboard/activity-logs?critical=1')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.critical.label', 'تعديل رسوم درجة تصنيف')
            ->assertJsonPath('items.0.critical.category_label', 'رسوم العضوية')
            ->assertJsonPath('items.0.critical.changes.0.label', 'رسوم التسجيل (د.أ)');

        $this->getJson('/api/v1/dashboard/activity-logs/critical-summary')
            ->assertOk()
            ->assertJsonPath('items.total', 1)
            ->assertJsonPath('items.categories.0.count', 1);
    }
}
