<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * قاعدة eslam (10/10/2026): "تسجيل الدفعات يحسب من الأقدم إلى الأحدث". كل مسار بيوزّع مبلغ على
 * الذمم بيسدّ الأقدم أولاً (بالسنة، بعدين تاريخ الاستحقاق، بعدين الأقدم إدخالاً)، والغرامات بعد الذمم.
 * الاستثناء الوحيد: زر "تسوية" على ذمة محددة.
 *
 * السيناريو الأساسي: عليه 150 لـ2025 و150 لـ2026. دفعة 150 بتسدّ 2025، ودفعة 200 بتسدّ 2025
 * كاملة و50 من 2026.
 */
class AllPaymentPathsSettleOldestFirstTest extends TestCase
{
    use RefreshDatabase;

    private Contractor $contractor;
    private User $admin;
    private ContractorDue $due2025;
    private ContractorDue $due2026;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->contractor = Contractor::create([
            'name'              => 'شركة الأقدم أولاً',
            'membership_number' => '975_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);

        // 2026 انضافت قبل 2025 (id أصغر) عشان نتأكد إن الترتيب بالسنة مش بالإدخال
        $this->due2026 = $this->due(2026, 150, '2026-01-01');
        $this->due2025 = $this->due(2025, 150, '2025-01-01');
    }

    private function due(?int $year, float $amount, ?string $dueDate = null): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $this->contractor->id,
            'year'          => $year,
            'description'   => 'ذمة ' . ($year ?? 'بلا سنة') . ' ' . ($dueDate ?? ''),
            'amount_jod'    => $amount,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
            'due_date'      => $dueDate,
        ]);
    }

    private function asAdmin(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
    }

    private function assertRemaining(float $r2025, float $r2026): void
    {
        $this->assertEquals($r2025, $this->due2025->fresh()->remaining_jod, 'المتبقي على 2025');
        $this->assertEquals($r2026, $this->due2026->fresh()->remaining_jod, 'المتبقي على 2026');
    }

    private function appPayment(float $amount, array $extra = []): int
    {
        Sanctum::actingAs($this->contractor, ['*']);

        return $this->postJson('/api/v1/contractor/payments/transfer', $extra + [
            'amount'        => $amount,
            'receipt_image' => UploadedFile::fake()->image('r.jpg'),
        ])->assertCreated()->json('items.id');
    }

    private function confirm(int $paymentId): void
    {
        $this->asAdmin();
        $this->postJson("/api/v1/payments/transactions/{$paymentId}/confirm")->assertOk();
    }

    private function changeStatus(int $paymentId, string $status): void
    {
        $this->asAdmin();
        $this->postJson("/api/v1/payments/transactions/{$paymentId}/status", [
            'status' => $status,
            'reason' => 'اختبار الترتيب',
        ])->assertOk();
    }

    // ── (1) تسجيل دفعة يدوي من الداشبورد ─────────────────────────────

    public function test_dashboard_dues_page_payment_150_settles_2025(): void
    {
        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/contractors/{$this->contractor->id}/dues/pay", ['amount' => 150])->assertSuccessful();

        $this->assertRemaining(0, 150);
    }

    public function test_dashboard_dues_page_payment_200_settles_2025_then_50_of_2026(): void
    {
        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/contractors/{$this->contractor->id}/dues/pay", ['amount' => 200])->assertSuccessful();

        $this->assertRemaining(0, 100);
    }

    public function test_payments_log_manual_payment_200_settles_oldest_first(): void
    {
        $this->asAdmin();
        $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id' => $this->contractor->id,
            'amount'        => 200,
            'method'        => 'cash',
        ])->assertSuccessful();

        $this->assertRemaining(0, 100);
    }

    // ── (2) دفعات التطبيق عند التأكيد ────────────────────────────────

    public static function appPaymentTypes(): array
    {
        return [
            'سداد ذمم'                   => [['type' => 'dues_payment'], false],
            'سداد ذمم من كرت ذمة 2026'   => [['type' => 'dues_payment'], true],
            'رسوم عضوية'                 => [['type' => 'membership_fee'], false],
            'رسوم عضوية من كرت ذمة 2026' => [['type' => 'membership_fee'], true],
            'دفعة مقدمة'                 => [['type' => 'advance_payment'], false],
            'بدون نوع'                   => [[], false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('appPaymentTypes')]
    public function test_app_payment_200_settles_2025_then_50_of_2026(array $extra, bool $from2026Card): void
    {
        if ($from2026Card) {
            $extra['contractor_due_id'] = $this->due2026->id;
        }

        $this->confirm($this->appPayment(200, $extra));

        $this->assertRemaining(0, 100);
    }

    public function test_app_penalty_payment_without_a_linked_penalty_settles_dues_oldest_first(): void
    {
        $this->confirm($this->appPayment(150, ['type' => 'penalty_payment']));

        $this->assertRemaining(0, 150);
    }

    // ── (3) الرصيد الدائن على ذمة/غرامة جديدة ─────────────────────────

    public function test_new_penalty_spends_existing_credit_on_the_oldest_due_first(): void
    {
        ContractorCredit::create([
            'contractor_id' => $this->contractor->id,
            'amount_jod'    => 150,
            'used_jod'      => 0,
            'description'   => 'رصيد سابق',
            'source'        => 'manual',
        ]);

        $this->asAdmin();
        $penaltyId = $this->postJson('/api/v1/penalties', [
            'contractor_id' => $this->contractor->id,
            'reason'        => 'مخالفة',
            'amount'        => 40,
        ])->assertSuccessful()->json('items.id');

        $this->assertRemaining(0, 150);
        $this->assertSame('unpaid', \App\Models\Penalty::findOrFail($penaltyId)->status, 'الغرامة بعد الذمم');
    }

    // ── (4) زيادة الرصيد يدوياً من صفحة الأرصدة ──────────────────────

    public function test_balance_increase_200_settles_2025_then_50_of_2026(): void
    {
        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/balances/{$this->contractor->id}/adjust", [
            'mode' => 'increase', 'amount' => 200, 'reason' => 'تصحيح',
        ])->assertOk();

        $this->assertRemaining(0, 100);
    }

    // ── (5) التراجع عن دفعة وإعادة تأكيدها ───────────────────────────

    public function test_reverted_payment_reconfirmed_settles_the_oldest_open_due(): void
    {
        $first  = $this->appPayment(150, ['type' => 'dues_payment']);
        $second = $this->appPayment(150, ['type' => 'dues_payment']);
        $this->confirm($first);   // ← 2025
        $this->confirm($second);  // ← 2026
        $this->assertRemaining(0, 0);

        $this->changeStatus($first, 'pending');
        $this->assertRemaining(150, 0);

        $this->changeStatus($first, 'paid');
        $this->assertRemaining(0, 0);
        $this->assertEquals([$this->due2025->id], Payment::findOrFail($first)->allocations()->pluck('contractor_due_id')->all());
    }

    public function test_reverting_the_older_payment_then_confirming_a_new_one_refills_2025(): void
    {
        $first = $this->appPayment(150, ['type' => 'dues_payment']);
        $this->confirm($first);
        $this->changeStatus($first, 'rejected');
        $this->assertRemaining(150, 150);

        $this->confirm($this->appPayment(200, ['type' => 'dues_payment']));
        $this->assertRemaining(0, 100);
    }

    // ── (6) تفاصيل الترتيب ───────────────────────────────────────────

    public function test_same_year_dues_are_settled_by_due_date_before_entry_order(): void
    {
        $this->due2025->delete();
        $this->due2026->delete();
        $late  = $this->due(2026, 100, '2026-06-01');
        $early = $this->due(2026, 100, '2026-02-01'); // انضافت بعدين بس استحقاقها أبكر

        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/contractors/{$this->contractor->id}/dues/pay", ['amount' => 100])->assertSuccessful();

        $this->assertSame('paid', $early->fresh()->status);
        $this->assertSame('unpaid', $late->fresh()->status);
    }

    public function test_due_without_year_counts_by_its_due_date_not_last(): void
    {
        $old = $this->due(null, 100, '2024-03-01');

        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/contractors/{$this->contractor->id}/dues/pay", ['amount' => 100])->assertSuccessful();

        $this->assertSame('paid', $old->fresh()->status, 'ذمة 2024 بلا سنة هي الأقدم');
        $this->assertRemaining(150, 150);
    }

    public function test_accumulated_2025_and_before_are_settled_year_by_year_before_2026(): void
    {
        $due2023 = $this->due(2023, 100, '2023-01-01');
        $due2024 = $this->due(2024, 100, '2024-01-01');

        $this->confirm($this->appPayment(250, ['type' => 'dues_payment', 'contractor_due_id' => $this->due2026->id]));

        $this->assertSame('paid', $due2023->fresh()->status);
        $this->assertSame('paid', $due2024->fresh()->status);
        $this->assertRemaining(100, 150);
    }

    public function test_settle_button_still_targets_the_chosen_due(): void
    {
        $this->asAdmin();
        $this->postJson("/api/v1/dashboard/dues/{$this->due2026->id}/settle", ['amount_jod' => 150])->assertOk();

        $this->assertRemaining(150, 0);
    }
}
