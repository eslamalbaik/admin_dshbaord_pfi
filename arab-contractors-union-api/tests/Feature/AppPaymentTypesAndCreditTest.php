<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\User;
use App\Notifications\PenaltyAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * النظام المالي بالتطبيق (طلب 7/10/2026): أنواع الدفعة الأربعة، الدفعة المقدمة بدون ذمة،
 * صرف الرصيد السابق تلقائياً على الذمم/الغرامات الجديدة، كروت الغرامات، الملاحظات،
 * وسجل المدفوعات التفصيلي.
 */
class AppPaymentTypesAndCreditTest extends TestCase
{
    use RefreshDatabase;

    private Contractor $contractor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->contractor = Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '960_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    private function asContractor(): void
    {
        Sanctum::actingAs($this->contractor, ['*']);
    }

    private function submit(array $data)
    {
        $this->asContractor();

        return $this->postJson('/api/v1/contractor/payments/transfer', $data + [
            'receipt_image' => UploadedFile::fake()->image('r.jpg'),
        ]);
    }

    private function confirm(int $paymentId): void
    {
        $this->admin();
        $this->postJson("/api/v1/payments/transactions/{$paymentId}/confirm")->assertOk();
    }

    private function addDue(float $amount, int $year = 2026): ContractorDue
    {
        $this->admin();
        $id = $this->postJson('/api/v1/dashboard/dues', [
            'contractor_id' => $this->contractor->id,
            'description'   => "رسوم اشتراك سنة {$year}",
            'amount_jod'    => $amount,
            'year'          => $year,
            'due_date'      => now()->addMonth()->toDateString(),
        ])->assertStatus(201)->json('items.id');

        return ContractorDue::findOrFail($id);
    }

    private function balance(): array
    {
        $this->asContractor();

        return $this->getJson('/api/v1/contractor/balance')->assertOk()->json('items');
    }

    // ─── 6) الملاحظات ───

    public function test_notes_entered_in_app_are_returned(): void
    {
        $id = $this->submit(['amount' => 50, 'notes' => 'حوالة من بنك فلسطين'])
            ->assertStatus(201)
            ->assertJsonPath('items.notes', 'حوالة من بنك فلسطين')
            ->json('items.id');

        $this->getJson("/api/v1/contractor/payments/{$id}")->assertJsonPath('items.notes', 'حوالة من بنك فلسطين');
        $this->getJson('/api/v1/contractor/payments')->assertJsonPath('items.0.notes', 'حوالة من بنك فلسطين');
        $this->getJson('/api/v1/contractor/payments/transfer')->assertJsonPath('items.0.notes', 'حوالة من بنك فلسطين');
    }

    public function test_notes_sent_under_another_field_name_are_kept(): void
    {
        $this->submit(['amount' => 50, 'note' => 'ملاحظة باسم note'])
            ->assertStatus(201)
            ->assertJsonPath('items.notes', 'ملاحظة باسم note');
    }

    // ─── 2) نوع الدفعة ───

    public function test_app_payment_types_have_arabic_labels_and_titles(): void
    {
        $expected = [
            'dues_payment'    => 'سداد ذمة',
            'membership_fee'  => 'رسوم اشتراك',
            'penalty_payment' => 'دفع غرامة',
            'advance_payment' => 'دفعة مقدمة',
        ];

        foreach ($expected as $type => $label) {
            $this->submit(['amount' => 10, 'type' => $type])
                ->assertStatus(201)
                ->assertJsonPath('items.type', $type)
                ->assertJsonPath('items.type_label', $label)
                ->assertJsonPath('items.title', $label);
        }

        $this->getJson('/api/v1/contractor/financial')
            ->assertJsonPath('items.payment_types.3', ['value' => 'advance_payment', 'label' => 'دفعة مقدمة']);
    }

    public function test_type_aliases_are_normalized_and_unknown_types_ignored(): void
    {
        $this->submit(['amount' => 10, 'type' => 'penalty'])->assertJsonPath('items.type', 'penalty_payment');
        $this->submit(['amount' => 10, 'type' => 'advance'])->assertJsonPath('items.type', 'advance_payment');
        // نوع غير معروف = كأنه ما انبعت (ما عليه ذمم ← رسوم اشتراك متل قبل)
        $this->submit(['amount' => 10, 'type' => 'شي غريب'])->assertStatus(201)->assertJsonPath('items.type', 'membership_fee');
    }

    // ─── 4) دفعة مقدمة بدون ذمة ───

    public function test_advance_payment_without_dues_becomes_credit_without_renewing_membership(): void
    {
        $id = $this->submit(['amount' => 150, 'type' => 'advance_payment'])->assertStatus(201)->json('items.id');
        $this->confirm($id);

        $balance = $this->balance();
        $this->assertEquals(150, $balance['credit_jod']);
        $this->assertEquals(150, $balance['net_jod']);
        $this->assertEquals(0, $balance['amount_due_jod']);
        $this->assertSame('credit', $balance['position']);
        $this->assertSame(0, $this->contractor->memberships()->count());
    }

    public function test_advance_payment_is_accepted_while_dues_are_open_and_settles_them(): void
    {
        $due = ContractorDue::create([
            'contractor_id' => $this->contractor->id, 'year' => 2026, 'description' => 'رسوم اشتراك سنة 2026',
            'amount_jod' => 100, 'status' => 'unpaid', 'source' => 'manual',
        ]);

        $id = $this->submit(['amount' => 130, 'type' => 'advance_payment'])->assertStatus(201)->json('items.id');
        $this->confirm($id);

        $this->assertSame('paid', $due->fresh()->status);
        $this->assertEquals(30, $this->balance()['net_jod']);
    }

    // ─── 5) رصيد سابق + ذمة جديدة ───

    public function test_new_due_is_settled_from_previous_credit(): void
    {
        $id = $this->submit(['amount' => 60, 'type' => 'advance_payment'])->json('items.id');
        $this->confirm($id);

        $due = $this->addDue(100);

        $this->assertSame('partially_paid', $due->status);
        $this->assertEquals(60, (float) $due->paid_jod);
        $this->assertEquals(60, (float) Payment::find($id)->used_amount_jod);

        $balance = $this->balance();
        $this->assertEquals(0, $balance['credit_jod']);
        $this->assertEquals(40, $balance['amount_due_jod']);

        // تأكيد دفعة ثانية بيكمّل الذمة، والفائض رصيد
        $second = $this->submit(['amount' => 50, 'type' => 'dues_payment'])->json('items.id');
        $this->confirm($second);
        $this->assertSame('paid', $due->fresh()->status);
        $this->assertEquals(10, $this->balance()['net_jod']);
    }

    public function test_credit_fully_covering_a_new_due_marks_it_paid(): void
    {
        ContractorCredit::create(['contractor_id' => $this->contractor->id, 'amount_jod' => 500, 'used_jod' => 0, 'description' => 'رصيد من كشف الدفعات']);

        $due = $this->addDue(200);

        $this->assertSame('paid', $due->status);
        $this->assertEquals(300, $this->balance()['credit_jod']);
    }

    public function test_annual_fee_settled_from_credit_activates_membership_for_its_year(): void
    {
        $id = $this->submit(['amount' => 300, 'type' => 'advance_payment'])->json('items.id');
        $this->confirm($id);

        $due = $this->addDue(200, 2027);
        $this->assertSame('paid', $due->status);

        $membership = $this->contractor->memberships()->where('status', 'active')->sole();
        $this->assertSame('2027-01-01', $membership->starts_at->toDateString());
        $this->assertSame('2027-12-31', $membership->expires_at->toDateString());

        // ترجيع الدفعة بيرجّع الذمة مستحقة وبيلغي العضوية اللي فعّلها الرصيد
        $this->admin();
        $this->postJson("/api/v1/payments/transactions/{$id}/status", ['status' => 'pending', 'reason' => 'خطأ بالمبلغ'])->assertOk();

        $this->assertSame('unpaid', $due->fresh()->status);
        $this->assertSame('rejected', $membership->fresh()->status);
    }

    public function test_partial_credit_on_annual_fee_does_not_activate_membership(): void
    {
        $id = $this->submit(['amount' => 50, 'type' => 'advance_payment'])->json('items.id');
        $this->confirm($id);

        $this->addDue(200, 2027);

        $this->assertFalse($this->contractor->memberships()->where('status', 'active')->exists());
    }

    public function test_reverting_the_payment_reverses_the_credit_settlement(): void
    {
        $id = $this->submit(['amount' => 100, 'type' => 'advance_payment'])->json('items.id');
        $this->confirm($id);
        $due = $this->addDue(100);
        $this->assertSame('paid', $due->status);

        $this->admin();
        $this->postJson("/api/v1/payments/transactions/{$id}/status", ['status' => 'pending', 'reason' => 'خطأ بالمبلغ'])->assertOk();

        $this->assertSame('unpaid', $due->fresh()->status);
        $this->assertEquals(100, $this->balance()['amount_due_jod']);
    }

    // ─── 3) الغرامات ───

    public function test_penalty_counts_in_total_appears_as_card_and_notifies(): void
    {
        $this->admin();
        $penaltyId = $this->postJson('/api/v1/penalties', [
            'contractor_id' => $this->contractor->id, 'reason' => 'تأخير تسليم وثائق', 'amount' => 40,
        ])->assertStatus(201)->json('items.id');

        Notification::assertSentTo($this->contractor, PenaltyAddedNotification::class);

        $this->asContractor();
        $financial = $this->getJson('/api/v1/contractor/financial')->assertOk()->json('items');
        $this->assertSame('40.00', $financial['summary']['total_due_jod']);
        $this->assertSame('40.00', $financial['summary']['amount_due_jod']);
        $this->assertSame(1, $financial['dues_counts']['penalties']);
        $this->assertSame($penaltyId, $financial['penalties'][0]['id']);
        $this->assertSame('تأخير تسليم وثائق', $financial['penalties'][0]['title']);
        $this->assertEquals(40, $financial['penalties'][0]['remaining_jod']);

        $this->getJson('/api/v1/contractor/home')->assertJsonPath('items.financial.balance', '40.00');
    }

    public function test_paying_a_penalty_card_settles_it_and_keeps_surplus_as_credit(): void
    {
        $penalty = Penalty::create(['contractor_id' => $this->contractor->id, 'reason' => 'مخالفة', 'amount' => 40, 'status' => 'unpaid']);

        $id = $this->submit(['amount' => 50, 'penalty_id' => $penalty->id])
            ->assertStatus(201)
            ->assertJsonPath('items.type', 'penalty_payment')
            ->assertJsonPath('items.penalty_id', $penalty->id)
            ->assertJsonPath('items.title', 'مخالفة')
            ->json('items.id');

        $this->getJson('/api/v1/contractor/financial')->assertJsonPath('items.penalties.0.is_under_review', true);
        // إشعار ثاني لنفس الغرامة وهي قيد المراجعة ممنوع
        $this->submit(['amount' => 40, 'penalty_id' => $penalty->id])->assertStatus(409);

        $this->confirm($id);

        $this->assertSame('paid', $penalty->fresh()->status);
        $this->assertEquals(10, $this->balance()['net_jod']);
    }

    public function test_new_penalty_is_settled_from_previous_credit(): void
    {
        $id = $this->submit(['amount' => 25, 'type' => 'advance_payment'])->json('items.id');
        $this->confirm($id);

        $this->admin();
        $this->postJson('/api/v1/penalties', [
            'contractor_id' => $this->contractor->id, 'reason' => 'تأخير', 'amount' => 40,
        ])->assertStatus(201)->assertJsonPath('items.status', 'partially_paid');

        $this->assertEquals(15, $this->balance()['amount_due_jod']);
    }

    // ─── 5) سجل المدفوعات التفصيلي ───

    public function test_statement_lists_every_movement_with_running_balance_matching_net(): void
    {
        // ذمة قديمة مسدَّدة بدون سجل توزيع (قبل 6/10) + ذمة جديدة + غرامة + دفعة + رصيد
        ContractorDue::create([
            'contractor_id' => $this->contractor->id, 'year' => 2025, 'description' => 'رسوم اشتراك سنة 2025',
            'amount_jod' => 80, 'paid_jod' => 80, 'status' => 'paid', 'source' => 'manual',
        ]);
        $id = $this->submit(['amount' => 70, 'type' => 'advance_payment', 'reference_number' => 'BANK-77', 'notes' => 'دفعة مقدمة'])->json('items.id');
        $this->confirm($id);
        $due = $this->addDue(100);
        Penalty::create(['contractor_id' => $this->contractor->id, 'reason' => 'مخالفة', 'amount' => 20, 'status' => 'unpaid']);
        $this->submit(['amount' => 15, 'type' => 'dues_payment']); // قيد المراجعة — ما بتحرّك الرصيد

        $this->asContractor();
        $statement = $this->getJson('/api/v1/contractor/payments/statement')->assertOk()->json('items');

        $net = $this->balance()['net_jod'];
        $this->assertEquals(-50, $net); // 70 − 100 − 20
        $this->assertEquals($net, $statement['summary']['net_jod']);
        $this->assertEquals(50, $statement['summary']['amount_due_jod']);
        $this->assertEquals($net, $statement['entries'][0]['balance_after_jod'], 'آخر حركة = الصافي');

        $payment = collect($statement['entries'])->firstWhere(fn ($e) => $e['kind'] === 'payment' && $e['id'] === $id);
        $this->assertSame('دفعة مقدمة', $payment['title']);
        $this->assertSame('BANK-77', $payment['bank_reference_number']);
        $this->assertNotEmpty($payment['reference_number']);
        $this->assertSame($due->id, $payment['allocations'][0]['id']);
        $this->assertEquals(70, $payment['allocations'][0]['amount_jod']);

        $this->assertNotNull(collect($statement['entries'])->firstWhere('kind', 'settlement'), 'تسديد 2025 القديم بيبين');
        $pending = collect($statement['entries'])->firstWhere('status', 'pending');
        $this->assertFalse($pending['counts_in_balance']);

        // نفس السجل للمحاسب
        $this->admin();
        $this->getJson("/api/v1/dashboard/balances/{$this->contractor->id}/statement")
            ->assertOk()
            ->assertJsonPath('items.summary.net_jod', -50);
    }
}
