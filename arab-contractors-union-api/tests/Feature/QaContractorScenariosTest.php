<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * سيناريوهات QA لطرف المقاول (صفحة "اختبارات الأمور المالية"، المجموعات T وR وM وS) —
 * كل اختبار باسم رقم السيناريو. المقاول يرفع التحويل من التطبيق، والأدمن يؤكّده من الداشبورد.
 */
class QaContractorScenariosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function contractor(string $mn, string $status = 'active'): Contractor
    {
        return Contractor::create([
            'name' => "شركة {$mn}", 'membership_number' => $mn, 'status' => $status, 'is_frozen' => false,
        ]);
    }

    private function due(Contractor $c, float $amount, int $year = 2026, string $desc = 'رسوم'): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $c->id, 'year' => $year, 'description' => "{$desc} {$year}",
            'amount_jod' => $amount, 'paid_jod' => 0, 'status' => 'unpaid', 'source' => 'manual',
            'due_date' => "{$year}-06-01",
        ]);
    }

    private function transfer(Contractor $c, float $amount, array $extra = [])
    {
        Sanctum::actingAs($c, ['*']);

        return $this->postJson('/api/v1/contractor/payments/transfer', $extra + [
            'amount' => $amount, 'receipt_image' => UploadedFile::fake()->image('r.jpg'),
        ]);
    }

    private function confirm(int $paymentId): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson("/api/v1/payments/transactions/{$paymentId}/confirm")->assertOk();
    }

    private function balance(Contractor $c): array
    {
        Sanctum::actingAs($c, ['*']);

        return $this->getJson('/api/v1/contractor/balance')->assertOk()->json('items');
    }

    private function adminRow(Contractor $c): array
    {
        Sanctum::actingAs($this->admin, ['*']);

        return $this->getJson('/api/v1/dashboard/balances?search=' . $c->membership_number)->assertOk()->json('items.0');
    }

    // ── R: الجبر من طرف المقاول ─────────────────────────────

    public function test_R09_home_last_invoice_is_not_the_rounding_due(): void
    {
        $c = $this->contractor('960_g');
        $this->due($c, 100.40);

        Sanctum::actingAs($c, ['*']);
        $home = $this->getJson('/api/v1/contractor/home')->assertOk()->json('items.financial');

        $this->assertEquals(100.40, (float) $home['last_invoice']['amount_jod']);
        $this->assertEquals(-101, $this->balance($c)['net_jod']);
    }

    public function test_R10_app_balance_matches_the_dashboard(): void
    {
        $c = $this->contractor('961_g');
        $this->due($c, 349.20);

        $mine = $this->balance($c);
        $row  = $this->adminRow($c);

        $this->assertSame($row['net_jod'], $mine['net_jod']);
        $this->assertIsInt($mine['net_jod']);
        $this->assertSame($row['status'], $mine['subscription_status']);
    }

    // ── T: تحويلات التطبيق ─────────────────────────────

    public function test_T01_untyped_transfer_with_open_dues_is_a_dues_payment(): void
    {
        $c = $this->contractor('962_g');
        $this->due($c, 11);

        $id = $this->transfer($c, 69)->assertCreated()->json('items.id');
        $this->confirm($id);

        $this->assertSame('dues_payment', Payment::find($id)->type);
        $this->assertEquals(58, $this->balance($c)['net_jod']);
        $this->assertSame(0, $c->memberships()->where('status', 'active')->count());
    }

    public function test_T02_pay_now_on_newer_due_settles_the_older_one(): void
    {
        $c = $this->contractor('963_g');
        $d25 = $this->due($c, 150, 2025);
        $d26 = $this->due($c, 150, 2026);

        $id = $this->transfer($c, 150, ['contractor_due_id' => $d26->id])->assertCreated()->json('items.id');
        $this->confirm($id);

        $this->assertSame('paid', $d25->fresh()->status);
        $this->assertSame('unpaid', $d26->fresh()->status);
    }

    public function test_T03_second_transfer_for_the_same_due_is_refused(): void
    {
        $c = $this->contractor('964_g');
        $d = $this->due($c, 100);

        $this->transfer($c, 100, ['contractor_due_id' => $d->id])->assertCreated();
        $this->transfer($c, 100, ['contractor_due_id' => $d->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'يوجد إشعار تحويل لهذه الذمة قيد المراجعة بالفعل.');
    }

    public function test_T04_transfer_for_a_paid_due_is_refused(): void
    {
        $c = $this->contractor('965_g');
        $d = $this->due($c, 100);
        $d->update(['paid_jod' => 100, 'status' => 'paid']);

        $this->transfer($c, 100, ['contractor_due_id' => $d->id])
            ->assertStatus(409)
            ->assertJsonPath('message', 'هذه الذمة مسدَّدة بالكامل مسبقاً.');
    }

    /** قاعدة c570c7f: دفعة كرت الغرامة كمان بتسدّ الذمم الأقدم أولاً، بعدين الغرامة، والباقي رصيد */
    public function test_T05_penalty_card_transfer_settles_dues_before_the_penalty(): void
    {
        $c = $this->contractor('966_g');
        $d = $this->due($c, 100);
        $p = Penalty::create(['contractor_id' => $c->id, 'reason' => 'تأخير', 'amount' => 50, 'paid_amount' => 0, 'status' => 'unpaid']);

        $id = $this->transfer($c, 120, ['penalty_id' => $p->id])->assertCreated()->json('items.id');
        $this->confirm($id);

        $this->assertSame('paid', $d->fresh()->status);
        $this->assertSame('partially_paid', $p->fresh()->status);
        $this->assertEquals(20, (float) $p->fresh()->paid_amount);
        $this->assertEquals(-30, $this->balance($c)['net_jod']);
    }

    public function test_T06_advance_payment_becomes_credit_then_settles_a_new_due(): void
    {
        $c = $this->contractor('967_g');

        $id = $this->transfer($c, 200, ['type' => 'advance_payment'])->assertCreated()->json('items.id');
        $this->confirm($id);
        $this->assertEquals(200, $this->balance($c)['net_jod']);

        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson('/api/v1/dashboard/dues', [
            'contractor_id' => $c->id, 'description' => 'رسوم 2026', 'amount_jod' => 150, 'year' => 2026,
            'due_kind' => 'new', 'due_date' => now()->addDays(5)->toDateString(),
        ])->assertCreated();

        $this->assertEquals(50, $this->balance($c)['net_jod']);
    }

    public function test_T07_membership_fee_is_blocked_by_open_dues_when_enforced(): void
    {
        Setting::set('enforce_dues_blocking', '1');
        $c = $this->contractor('968_g');
        $this->due($c, 100);

        $this->transfer($c, 300, ['type' => 'membership_fee'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'لا يمكن إتمام العملية قبل تسوية المتطلبات المستحقّة.');

        // سداد الذمم والدفعة المقدمة يبقيان مسموحين
        $this->transfer($c, 50, ['type' => 'dues_payment'])->assertCreated();
        $this->transfer($c, 50, ['type' => 'advance_payment'])->assertCreated();
    }

    public function test_T08_suspended_contractor_can_use_the_app_but_not_pay_membership(): void
    {
        $c = $this->contractor('969_g', 'suspended');

        Sanctum::actingAs($c, ['*']);
        $this->getJson('/api/v1/contractor/home')->assertOk();

        $this->transfer($c, 300, ['type' => 'membership_fee'])->assertStatus(403);
    }

    public function test_T09_cannot_pay_another_contractors_due(): void
    {
        $owner = $this->contractor('970_g');
        $other = $this->contractor('971_g');
        $d = $this->due($owner, 100);

        $this->transfer($other, 100, ['contractor_due_id' => $d->id])
            ->assertForbidden()
            ->assertJsonPath('message', 'هذه الذمة لا تعود لحسابك.');
    }

    // ── M: العضوية من طرف المقاول ─────────────────────────────

    public function test_M05_app_subscription_status_matches_the_dashboard(): void
    {
        $paid  = $this->contractor('972_g');
        $owing = $this->contractor('973_g');
        $this->due($owing, 500);

        foreach ([$paid, $owing] as $c) {
            $this->assertSame($this->adminRow($c)['status'], $this->balance($c)['subscription_status']);
        }
        $this->assertSame('active', $this->balance($paid)['subscription_status']);
        $this->assertSame('expired', $this->balance($owing)['subscription_status']);
    }

    public function test_M06_membership_certificate_is_not_blocked_for_a_95_percent_payer(): void
    {
        $c = $this->contractor('974_g');
        $d = $this->due($c, 640);
        $d->update(['paid_jod' => 608, 'status' => 'partially_paid']);
        $this->assertSame('active', $this->balance($c)['subscription_status']);

        Sanctum::actingAs($c, ['*']);
        $res = $this->postJson('/api/v1/contractor/certificate-requests', ['type' => 'membership']);

        // ممكن يرفض لسبب ثاني (ملف تعريفي ناقص مثلاً)، بس مش لأن العضوية "غير سارية"
        $this->assertNotSame('membership_inactive', $res->json('error_code') ?? $res->json('code'));
        $this->assertStringNotContainsString('غير سارية', (string) $res->json('message'));
    }

    // ── S: سجل المدفوعات بالتطبيق ─────────────────────────────

    public function test_S04_app_statement_matches_the_dashboard(): void
    {
        $c = $this->contractor('975_g');
        $this->due($c, 300);
        $id = $this->transfer($c, 120)->assertCreated()->json('items.id');
        $this->confirm($id);

        Sanctum::actingAs($c, ['*']);
        $app = $this->getJson('/api/v1/contractor/payments/statement')->assertOk()->json('items');

        Sanctum::actingAs($this->admin, ['*']);
        $dash = $this->getJson("/api/v1/dashboard/balances/{$c->id}/statement")->assertOk()->json('items');

        $this->assertEquals($dash['summary'], $app['summary']);
        $this->assertCount(count($dash['entries']), $app['entries']);
        $payment = collect($app['entries'])->firstWhere('kind', 'payment');
        $this->assertNotEmpty($payment['datetime'] ?? null, 'وقت وصول التحويل ظاهر');
    }
}
