<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * سيناريوهات قائمة الاختبار 10/10 (المشاكل #1 و#2 و#6) على مقاول واحد:
 * ذمة 2026 = 200 وذمة 2025 = 300.
 */
class DiscountPreviewAndActive95ScenarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-10 12:00:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function contractor(string $status = 'active'): Contractor
    {
        return Contractor::create([
            'name'                => 'nancy abo alkamar',
            'membership_number'   => '9565_g',
            'commercial_register' => '512345678',
            'status'              => $status,
            'is_frozen'           => false,
        ]);
    }

    private function due(Contractor $c, float $amount, int $year, string $description): ContractorDue
    {
        $this->postJson('/api/v1/dashboard/dues', [
            'contractor_id' => $c->id,
            'description'   => $description,
            'amount_jod'    => $amount,
            'year'          => $year,
            'due_date'      => now()->addDay()->toDateString(),
        ])->assertCreated();

        return $c->dues()->latest('id')->first();
    }

    private function preview(ContractorDue $due, string $type, float $value): array
    {
        return $this->postJson('/api/v1/dashboard/dues/discount/bulk', [
            'mode' => 'ids', 'ids' => [$due->id], 'discount_type' => $type, 'discount_value' => $value, 'dry_run' => true,
        ])->assertOk()->json('items');
    }

    private function discount(ContractorDue $due, string $type, float $value): void
    {
        $this->postJson("/api/v1/dashboard/dues/{$due->id}/discount", [
            'discount_type' => $type, 'discount_value' => $value,
        ])->assertOk();
    }

    /** #1: بعد خصم 10% (200 ← 180)، معاينة خصم ثابت 20 لازم تبيّن أثر 20 مش 40، والنتيجة 160 */
    public function test_fixed_discount_preview_after_percent_shows_only_its_own_impact(): void
    {
        $c = $this->contractor();
        $due2026 = $this->due($c, 200, 2026, 'رسوم عضوية');
        $this->due($c, 300, 2025, 'رسوم 2025');

        $this->assertEquals(20, $this->preview($due2026, 'percent', 10)['total_discount_impact_jod']);
        $this->discount($due2026, 'percent', 10);
        $this->assertEquals(180, (float) $due2026->fresh()->amount_jod);

        $this->assertEquals(20, $this->preview($due2026, 'fixed', 20)['total_discount_impact_jod']);
        $this->discount($due2026, 'fixed', 20);
        $this->assertEquals(160, (float) $due2026->fresh()->amount_jod);
    }

    /** #2: خصم ثابت 20 على ذمة 2025 (300 ← 280) بيبيّن بالداشبورد والتطبيق كخصم بالدينار مش نسبة */
    public function test_fixed_discount_is_shown_as_fixed_jod_on_dashboard_and_app(): void
    {
        $c = $this->contractor();
        $this->due($c, 200, 2026, 'رسوم عضوية');
        $due2025 = $this->due($c, 300, 2025, 'رسوم 2025');

        $this->discount($due2025, 'fixed', 20);

        $row = collect($this->getJson("/api/v1/dashboard/dues?contractor_id={$c->id}&per_page=50")->assertOk()->json('items'))
            ->firstWhere('id', $due2025->id);
        $this->assertSame('رسوم 2025', $row['description']);
        $this->assertSame('fixed', $row['discount_type']);
        $this->assertEquals(20, $row['discount_amount_jod']);
        $this->assertEquals(300, $row['original_amount_jod']);
        $this->assertSame('خصم 20 د.أ', $row['discount_label']);

        Sanctum::actingAs($c, ['*']);
        $card = collect($this->getJson('/api/v1/contractor/financial')->assertOk()->json('items.dues'))
            ->firstWhere('id', $due2025->id);
        $this->assertSame('رسوم 2025', $card['description']);
        $this->assertSame('fixed', $card['discount_type']);
        $this->assertEquals(300, $card['original_amount_jod']);
        $this->assertEquals(280, $card['amount_jod']);
        $this->assertSame('خصم 20 د.أ', $card['discount_label']);
    }

    /**
     * #6: الذمم 160 + 280 والغرامة 200 = 640؛ انسدّ 608 (95% بالضبط) والمتبقي 32 ← "فعّالة"
     * بصفحة الأرصدة وقائمة المقاولين والتطبيق، سواء حالته بالنظام "فعّال" أو "منتهي".
     */
    public function test_exactly_95_percent_paid_shows_active_everywhere(): void
    {
        foreach (['active', 'expired'] as $status) {
            $c = $this->contractor($status);
            ContractorDue::create(['contractor_id' => $c->id, 'year' => 2026, 'description' => 'رسوم عضوية (2026)',
                'amount_jod' => 160, 'paid_jod' => 160, 'status' => 'paid', 'source' => 'manual']);
            ContractorDue::create(['contractor_id' => $c->id, 'year' => 2025, 'description' => 'رسوم 2025',
                'amount_jod' => 280, 'paid_jod' => 280, 'status' => 'paid', 'source' => 'manual']);
            Penalty::create(['contractor_id' => $c->id, 'reason' => 'غرامة تأخير',
                'amount' => 200, 'paid_amount' => 168, 'status' => 'partially_paid']);

            $this->getJson('/api/v1/dashboard/balances?search=9565_g')->assertOk()
                ->assertJsonPath('items.0.net_jod', -32)
                ->assertJsonPath('items.0.status', 'active');
            $this->getJson('/api/v1/contractors?search=9565_g')->assertJsonPath('items.0.membership_status', 'active');

            $summary = $this->getJson("/api/v1/dashboard/balances/{$c->id}/statement")->assertOk()->json('items.summary');
            $this->assertEquals(640, $summary['total_obligations_jod']);
            $this->assertEquals(608, $summary['total_paid_jod']);
            $this->assertEquals(32, $summary['amount_due_jod']);

            Sanctum::actingAs($c, ['*']);
            $this->getJson('/api/v1/contractor/balance')->assertOk()->assertJsonPath('items.subscription_status', 'active');

            Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
            $c->forceDelete();
        }
    }

    /** منتهي قديم ما عليه ولا ذمة لهالسنة بيضل "منتهية" حتى لو رصيده صفر */
    public function test_expired_without_current_year_dues_stays_expired(): void
    {
        $c = $this->contractor('expired');
        ContractorDue::create(['contractor_id' => $c->id, 'year' => 2024, 'description' => 'رسوم 2024',
            'amount_jod' => 100, 'paid_jod' => 100, 'status' => 'paid', 'source' => 'manual']);

        $this->getJson('/api/v1/dashboard/balances?search=9565_g')->assertOk()->assertJsonPath('items.0.status', 'expired');
    }
}
