<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * سجل النشاط المالي (?financial=1) بيرجّع الأحداث المالية بس، وكل سجل بيحمل المقاول
 * المرتبط (اسم + رقم عضوية) حتى لو الـ meta ما فيها غير المعرّف أو ما فيها شي.
 */
class FinancialActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_filter_summary_and_contractor_resolution(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        $contractor = Contractor::create([
            'name' => 'شركة عياد للمقاولات', 'membership_number' => '9541_g', 'status' => 'active', 'is_frozen' => false,
        ]);
        $due = ContractorDue::create([
            'contractor_id' => $contractor->id, 'description' => 'رسوم 2026', 'amount_jod' => 100, 'status' => 'unpaid', 'year' => 2026,
        ]);

        AuditLogService::record($admin, 'due.updated', $due, ['amount_jod' => 100]);
        AuditLogService::record($admin, 'balance.adjusted', $contractor, ['contractor_id' => (string) $contractor->id]);
        AuditLogService::record($admin, 'grade_fee.updated', null, []);
        AuditLogService::record($admin, 'news.created', null, ['title' => 'خبر']);
        AuditLogService::record($admin, 'certificate.deleted', null, ['contractor_id' => (string) $contractor->id]);

        $items = $this->getJson('/api/v1/dashboard/activity-logs?financial=1')->assertOk()->json('items');
        $this->assertEqualsCanonicalizing(['due.updated', 'balance.adjusted', 'grade_fee.updated'], array_column($items, 'action'));
        $this->assertTrue(collect($items)->every(fn ($i) => $i['is_financial']));

        $dueLog = collect($items)->firstWhere('action', 'due.updated');
        $this->assertSame('شركة عياد للمقاولات', $dueLog['contractor']['name']);
        $this->assertSame('9541_g', $dueLog['contractor']['membership_number']);

        $all = collect($this->getJson('/api/v1/dashboard/activity-logs')->assertOk()->json('items'));
        $this->assertCount(5, $all);
        $this->assertSame('9541_g', $all->firstWhere('action', 'certificate.deleted')['contractor']['membership_number']);
        $this->assertFalse($all->firstWhere('action', 'news.created')['is_financial']);
        $this->assertNull($all->firstWhere('action', 'news.created')['contractor']);

        $this->getJson('/api/v1/dashboard/activity-logs/financial-summary')->assertOk()->assertJsonPath('items.total', 3);
        $this->assertEqualsCanonicalizing(
            ['balance.adjusted', 'due.updated', 'grade_fee.updated'],
            $this->getJson('/api/v1/dashboard/activity-logs/actions?financial=1')->json('items'),
        );
    }
}
