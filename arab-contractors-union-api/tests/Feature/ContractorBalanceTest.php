<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * GET contractor/balance — رصيد المقاول الصافي بتطبيق المقاول، بنفس حسبة شاشة الأرصدة بالداشبورد.
 */
class ContractorBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $membership): Contractor
    {
        return Contractor::create([
            'name'              => "شركة {$membership}",
            'membership_number' => $membership,
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    /** ذمة 100 + غرامة 20 مسدَّد منها 5، مقابل رصيد دائن 30 + فائض دفعة ذمم 10 */
    private function seedLedger(Contractor $c): void
    {
        ContractorDue::create([
            'contractor_id' => $c->id, 'description' => 'رسوم 2025', 'year' => 2025,
            'amount_jod' => 100, 'paid_jod' => 0, 'status' => 'unpaid',
        ]);
        Penalty::create([
            'contractor_id' => $c->id, 'reason' => 'غرامة تأخير',
            'amount' => 20, 'paid_amount' => 5, 'status' => 'partially_paid',
        ]);
        ContractorCredit::create([
            'contractor_id' => $c->id, 'amount_jod' => 30, 'used_jod' => 0, 'description' => 'دفعة مقدّمة',
        ]);
        Payment::create([
            'contractor_id' => $c->id, 'amount' => 50, 'currency' => 'JOD', 'amount_jod' => 50,
            'used_amount_jod' => 40, 'type' => 'dues_payment', 'status' => 'paid', 'method' => 'cash',
        ]);
    }

    public function test_returns_the_contractors_own_net_balance(): void
    {
        $c = $this->contractor('940_g');
        $this->seedLedger($c);
        $this->seedLedger($this->contractor('941_g')); // بيانات شركة ثانية ما لازم تدخل

        Sanctum::actingAs($c, ['*']);

        $this->getJson('/api/v1/contractor/balance')
            ->assertOk()
            ->assertJsonPath('items.contractor_id', $c->id)
            ->assertJsonPath('items.credit_jod', 40)
            ->assertJsonPath('items.dues_jod', 100)
            ->assertJsonPath('items.penalties_jod', 15)
            ->assertJsonPath('items.debit_jod', 115)
            ->assertJsonPath('items.net_jod', -75)
            ->assertJsonPath('items.position', 'owes')
            ->assertJsonPath('items.subscription_status', 'expired')
            ->assertJsonPath('items.subscription_status_label', 'منتهية')
            ->assertJsonPath('items.currency', 'JOD');
    }

    public function test_matches_the_admin_balances_screen(): void
    {
        $c = $this->contractor('942_g');
        $this->seedLedger($c);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
        $admin = $this->getJson('/api/v1/dashboard/balances?search=942_g')->assertOk()->json('items.0');

        Sanctum::actingAs($c, ['*']);
        $mine = $this->getJson('/api/v1/contractor/balance')->assertOk()->json('items');

        foreach (['credit_jod', 'dues_jod', 'penalties_jod', 'debit_jod', 'net_jod'] as $key) {
            $this->assertEquals($admin[$key], $mine[$key], $key);
        }
        $this->assertSame($admin['status'], $mine['subscription_status']);
    }

    /**
     * جبر الصافي لصالح الاتحاد: رصيد الشركة للأقل، والمطلوب منها للأكثر.
     */
    #[DataProvider('roundingCases')]
    public function test_net_is_rounded_in_the_unions_favour(float $credit, float $due, int $net, float $exact): void
    {
        $c = $this->contractor('944_g');
        if ($credit > 0) {
            ContractorCredit::create(['contractor_id' => $c->id, 'amount_jod' => $credit, 'used_jod' => 0, 'description' => 'رصيد']);
        }
        if ($due > 0) {
            ContractorDue::create([
                'contractor_id' => $c->id, 'description' => 'رسوم', 'year' => 2026,
                'amount_jod' => $due, 'paid_jod' => 0, 'status' => 'unpaid',
            ]);
        }

        Sanctum::actingAs($c, ['*']);

        $items = $this->getJson('/api/v1/contractor/balance')
            ->assertOk()
            ->assertJsonPath('items.net_jod', $net)
            ->json('items');

        // JSON بيرجع 300 مش 300.0 لما ما في كسور، فالمقارنة بالقيمة مش بالنوع
        $this->assertEquals($exact, $items['net_exact_jod']);
    }

    /** الجبر مخزَّن بقيد "جبر كسور الرصيد"، فالصافي الدقيق نفسه صار عدداً صحيحاً */
    public static function roundingCases(): array
    {
        return [
            'credit 300.20 → 300'   => [300.20, 0, 300, 300],
            'credit 300.50 → 300'   => [300.50, 0, 300, 300],
            'credit 300.99 → 300'   => [300.99, 0, 300, 300],
            'credit 0.50 → settled' => [0.50, 0, 0, 0],
            'owes 349.20 → 350'     => [0, 349.20, -350, -350],
            'owes 349.01 → 350'     => [0, 349.01, -350, -350],
            'owes 349.00 stays'     => [0, 349.00, -349, -349],
            'credit 300.00 stays'   => [300.00, 0, 300, 300],
        ];
    }

    public function test_balances_filter_uses_the_rounded_net(): void
    {
        $c = $this->contractor('945_g');
        ContractorCredit::create(['contractor_id' => $c->id, 'amount_jod' => 0.6, 'used_jod' => 0, 'description' => 'كسور']);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/balances?search=945_g&filter=zero')
            ->assertOk()->assertJsonCount(1, 'items');
        $this->getJson('/api/v1/dashboard/balances?search=945_g&filter=credit')
            ->assertOk()->assertJsonCount(0, 'items');
    }

    /** بدون أي عضوية مسجّلة ما في تاريخ انتهاء ← "منتهية"، حتى لو رصيده صفر */
    public function test_contractor_with_nothing_on_record_is_settled_but_expired(): void
    {
        Sanctum::actingAs($this->contractor('943_g'), ['*']);

        $this->getJson('/api/v1/contractor/balance')
            ->assertOk()
            ->assertJsonPath('items.net_jod', 0)
            ->assertJsonPath('items.position', 'settled')
            ->assertJsonPath('items.subscription_status', 'expired')
            ->assertJsonPath('items.subscription_status_label', 'منتهية');
    }

    /** الحالة حسب تاريخ انتهاء العضوية بس (قرار 2026-10-12): الرصيد والذمم ما بيأثروا */
    public function test_admin_screen_status_follows_expiry_only(): void
    {
        $endedWithCredit = $this->contractor('944_g');
        Membership::create([
            'contractor_id' => $endedWithCredit->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->subYear()->startOfYear(), 'expires_at' => now()->subYear()->endOfYear(),
        ]);
        ContractorCredit::create([
            'contractor_id' => $endedWithCredit->id, 'amount_jod' => 4400, 'used_jod' => 0, 'description' => 'رصيد',
        ]);

        $currentButOwes = $this->contractor('945_g');
        $this->seedLedger($currentButOwes);
        Membership::create([
            'contractor_id' => $currentButOwes->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->startOfYear(), 'expires_at' => now()->addMonth(),
        ]);

        $this->contractor('946_g'); // بدون عضوية

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/balances?search=944_g')->assertOk()->assertJsonPath('items.0.status', 'expired');
        $this->getJson('/api/v1/dashboard/balances?search=945_g')->assertOk()->assertJsonPath('items.0.status', 'active');
        $this->getJson('/api/v1/dashboard/balances?search=946_g')->assertOk()->assertJsonPath('items.0.status', 'expired');
    }

    /** أبعد تاريخ انتهاء هو المعتمد، واليوم نفسه لسا "فعّالة" */
    public function test_latest_expiry_wins_and_today_counts_as_active(): void
    {
        $today = $this->contractor('947_g');
        Membership::create([
            'contractor_id' => $today->id, 'type' => 'new', 'status' => 'active',
            'expires_at' => now()->subYear(),
        ]);
        Membership::create([
            'contractor_id' => $today->id, 'type' => 'renewal', 'status' => 'active',
            'expires_at' => now()->toDateString(),
        ]);

        $yesterday = $this->contractor('948_g');
        Membership::create([
            'contractor_id' => $yesterday->id, 'type' => 'new', 'status' => 'active',
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        // عضوية مرفوضة ما بتنحسب مهما كان تاريخها
        Membership::create([
            'contractor_id' => $yesterday->id, 'type' => 'renewal', 'status' => 'rejected',
            'expires_at' => now()->addYear(),
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/balances?search=947_g')->assertJsonPath('items.0.status', 'active');
        $this->getJson('/api/v1/dashboard/balances?search=948_g')->assertJsonPath('items.0.status', 'expired');
    }

    /** "معلّق" و"موقوف" إداريين بينعرضوا كما هم حتى لو تاريخ الانتهاء لسا ما فات */
    public function test_pending_and_suspended_are_shown_as_is(): void
    {
        foreach (['949_g' => 'pending', '953_g' => 'suspended'] as $number => $status) {
            $c = Contractor::create([
                'name' => "شركة {$number}", 'membership_number' => $number, 'status' => $status, 'is_frozen' => false,
            ]);
            Membership::create([
                'contractor_id' => $c->id, 'type' => 'new', 'status' => 'active', 'expires_at' => now()->addYear(),
            ]);
        }

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/balances?search=949_g')->assertJsonPath('items.0.status', 'pending');
        $this->getJson('/api/v1/dashboard/balances?search=953_g')->assertJsonPath('items.0.status', 'suspended');
    }

    /** صفحة المقاولين بتعرض نفس "حالة العضوية" تبع صفحة الأرصدة، مش حالة الحساب الإدارية */
    public function test_contractors_list_shows_same_membership_status_as_balances(): void
    {
        $owes = $this->contractor('950_g');
        $this->seedLedger($owes); // بدون عضوية
        $paid = $this->contractor('951_g');
        Membership::create([
            'contractor_id' => $paid->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->startOfYear(), 'expires_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        foreach (['950_g' => 'expired', '951_g' => 'active'] as $number => $expected) {
            $this->getJson("/api/v1/dashboard/balances?search={$number}")->assertJsonPath('items.0.status', $expected);
            $this->getJson("/api/v1/contractors?search={$number}")->assertOk()
                ->assertJsonPath('items.0.membership_status', $expected)
                ->assertJsonPath('items.0.status', 'active');
        }

        $this->getJson('/api/v1/contractors?search=951_g')
            ->assertJsonPath('items.0.membership_expires_at', now()->addMonth()->toDateString());
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/contractor/balance')->assertUnauthorized();
    }
}
