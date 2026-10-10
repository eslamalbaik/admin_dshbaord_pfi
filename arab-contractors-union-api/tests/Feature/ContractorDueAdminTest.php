<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-17 #7 (تاريخ الاستحقاق لا يُسبق اليوم إلا بتفعيل صريح) و #9 (بطاقات الملخّص تُصفَّر
 * فعلاً بحذف كل الذمم — تثبيت مسار الحذف الناعم حتى لا يُعاد تشخيصه).
 */
class ContractorDueAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function contractor(string $membership = '928_g'): Contractor
    {
        return Contractor::create([
            'name'                => "شركة {$membership} للمقاولات",
            'membership_number'   => $membership,
            'commercial_register' => (string) (500000000 + crc32($membership) % 99999999),
            'status'              => 'active',
            'is_frozen'           => false,
        ]);
    }

    private function payload(Contractor $contractor, array $extra = []): array
    {
        return array_merge([
            'contractor_id' => $contractor->id,
            'description'   => 'رسوم عضوية 2026',
            'amount_jod'    => 100,
            'year'          => 2026,
        ], $extra);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  #7 — تاريخ الاستحقاق
    // ─────────────────────────────────────────────────────────────────────────

    /** المقاول يُبلَّغ بالذمة مع تاريخ استحقاقها — ذمة بلا تاريخ أو سنة لا تُقبل. */
    public function test_due_date_and_year_are_required(): void
    {
        $this->actingAsAdmin();

        $payload = $this->payload($this->contractor());
        unset($payload['year']);

        $this->postJson('/api/v1/dashboard/dues', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date', 'year']);
    }

    public function test_a_past_due_date_is_rejected_without_the_backdate_flag(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor(), [
            'due_date' => now()->subMonth()->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('due_date');
    }

    /** Trello #13: الذمة الجديدة تاريخها من بكرة وما بعد — اليوم نفسه مرفوض. */
    public function test_a_new_due_rejects_today_and_accepts_tomorrow(): void
    {
        $this->actingAsAdmin();
        $today = now(config('app.local_timezone'));

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor('928_g'), [
            'due_kind' => 'new',
            'due_date' => $today->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('due_date');

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor('929_g'), [
            'due_kind' => 'new',
            'due_date' => $today->copy()->addDay()->toDateString(),
        ]))->assertCreated();
    }

    /** بدون due_kind (عميل أقدم) تُعامل الذمة جديدة: اليوم مرفوض. */
    public function test_missing_due_kind_is_treated_as_a_new_due(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor(), [
            'due_date' => now(config('app.local_timezone'))->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('due_date');
    }

    public function test_an_old_due_accepts_a_past_date_and_today_with_a_reason(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor('928_g'), [
            'due_kind'        => 'old',
            'due_date'        => '2023-06-01',
            'backdate_reason' => 'من السجل الورقي',
        ]))->assertCreated();

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor('929_g'), [
            'due_kind'        => 'old',
            'due_date'        => now(config('app.local_timezone'))->toDateString(),
            'backdate_reason' => 'من السجل الورقي',
        ]))->assertCreated();
    }

    public function test_an_old_due_rejects_a_future_date(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor(), [
            'due_kind'        => 'old',
            'due_date'        => now(config('app.local_timezone'))->addDays(3)->toDateString(),
            'backdate_reason' => 'سبب',
        ]))->assertStatus(422)->assertJsonValidationErrors('due_date');
    }

    public function test_an_old_due_requires_a_reason_and_a_valid_kind(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor(), [
            'due_kind' => 'old',
            'due_date' => '2024-01-15',
        ]))->assertStatus(422)->assertJsonValidationErrors('backdate_reason');

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor('929_g'), [
            'due_kind' => 'other',
            'due_date' => '2024-01-15',
        ]))->assertStatus(422)->assertJsonValidationErrors('due_kind');
    }

    public function test_a_past_due_date_is_accepted_with_the_backdate_flag_and_a_reason(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor(), [
            'due_date'        => '2024-01-15',
            'allow_backdate'  => true,
            'backdate_reason' => 'ذمة 2024 متأخّرة السداد، مُدرجة من السجل الورقي',
        ]))->assertCreated();

        $due = ContractorDue::findOrFail($response->json('items.id'));

        $this->assertSame('2024-01-15', $due->due_date->toDateString());
        // السبب يُحفظ بالملاحظات — لا عمود جديد، لكن يبقى أثر مكتوب يُسأل عنه
        $this->assertStringContainsString('مُدرجة من السجل الورقي', $due->notes);
    }

    public function test_the_backdate_flag_requires_a_reason(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor(), [
            'due_date'       => '2024-01-15',
            'allow_backdate' => true,
        ]))->assertStatus(422)->assertJsonValidationErrors('backdate_reason');
    }

    /** الحقلان ليسا عمودين بالجدول — تمريرهما لا يجوز أن يُسقط الإنشاء بخطأ عمود مجهول. */
    public function test_backdate_inputs_are_not_persisted_as_columns(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/dashboard/dues', $this->payload($this->contractor(), [
            'due_date'        => '2024-01-15',
            'allow_backdate'  => true,
            'backdate_reason' => 'سبب مكتوب',
        ]))->assertCreated();

        $this->assertDatabaseHas('contractor_dues', ['id' => $response->json('items.id')]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  #9 — الملخّص يُصفَّر بحذف كل الذمم
    // ─────────────────────────────────────────────────────────────────────────

    public function test_summary_returns_zeros_once_every_due_is_deleted(): void
    {
        $this->actingAsAdmin();

        $first  = $this->contractor('928_g');
        $second = $this->contractor('929_g');

        foreach ([$first, $second] as $contractor) {
            ContractorDue::create([
                'contractor_id' => $contractor->id,
                'year'          => 2026,
                'description'   => 'رسوم عضوية 2026',
                'amount_jod'    => 100,
                'paid_jod'      => 0,
                'status'        => 'unpaid',
                'source'        => 'manual',
            ]);
        }

        $before = $this->getJson('/api/v1/dashboard/dues/summary')->assertOk()->json('items');
        $this->assertSame(200.0, (float) $before['outstanding_total_jod']);
        $this->assertSame(2, $before['contractors_with_dues']);
        $this->assertSame(2, $before['outstanding_dues_count']);

        foreach (ContractorDue::pluck('id') as $id) {
            $this->deleteJson("/api/v1/dashboard/dues/{$id}")->assertOk();
        }

        $after = $this->getJson('/api/v1/dashboard/dues/summary')->assertOk()->json('items');

        $this->assertSame(0.0, (float) $after['outstanding_total_jod']);
        $this->assertSame(0.0, (float) $after['collected_total_jod']);
        $this->assertSame(0, $after['contractors_with_dues']);
        $this->assertSame(0, $after['outstanding_dues_count']);
        $this->assertSame(0, $after['dues_count']);
    }
}
