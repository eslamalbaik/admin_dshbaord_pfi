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

    public function test_contractor_with_nothing_on_record_is_settled(): void
    {
        Sanctum::actingAs($this->contractor('943_g'), ['*']);

        $this->getJson('/api/v1/contractor/balance')
            ->assertOk()
            ->assertJsonPath('items.net_jod', 0)
            ->assertJsonPath('items.position', 'settled')
            ->assertJsonPath('items.subscription_status', 'active')
            ->assertJsonPath('items.subscription_status_label', 'فعّالة');
    }

    /** الحالة حسب الرصيد بس: عضوية قديمة منتهية بدون ذمم = "فعّالة"، وعليه ذمم = "منتهية" */
    public function test_admin_screen_status_follows_balance_not_old_expiry(): void
    {
        $expired = $this->contractor('944_g');
        $this->seedLedger($expired);
        Membership::create([
            'contractor_id' => $expired->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->subYear()->startOfYear(), 'expires_at' => now()->subYear()->endOfYear(),
        ]);

        $current = $this->contractor('945_g');
        Membership::create([
            'contractor_id' => $current->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->startOfYear(), 'expires_at' => now()->endOfYear(),
        ]);

        $noMembership = $this->contractor('946_g');

        $endedNoDues = $this->contractor('952_g'); // متل "شركة عياد": عضوية 2025 منتهية، ورصيدها له
        Membership::create([
            'contractor_id' => $endedNoDues->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->subYear()->startOfYear(), 'expires_at' => now()->subYear()->endOfYear(),
        ]);
        ContractorCredit::create([
            'contractor_id' => $endedNoDues->id, 'amount_jod' => 4400, 'used_jod' => 0, 'description' => 'رصيد',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/balances?search=944_g')->assertOk()->assertJsonPath('items.0.status', 'expired');
        $this->getJson('/api/v1/dashboard/balances?search=945_g')->assertOk()->assertJsonPath('items.0.status', 'active');
        $this->getJson('/api/v1/dashboard/balances?search=946_g')->assertOk()->assertJsonPath('items.0.status', 'active');
        $this->getJson('/api/v1/dashboard/balances?search=952_g')->assertOk()->assertJsonPath('items.0.status', 'active');
    }

    /** عضويته سارية بس عليه رسوم (صافي سالب) بينعرض "منتهية" مش "فعّالة" */
    public function test_admin_screen_shows_expired_when_contractor_owes(): void
    {
        $owes = $this->contractor('947_g');
        $this->seedLedger($owes); // صافي -35
        Membership::create([
            'contractor_id' => $owes->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->startOfYear(), 'expires_at' => now()->endOfYear(),
        ]);

        $noMembershipOwes = $this->contractor('948_g');
        ContractorDue::create([
            'contractor_id' => $noMembershipOwes->id, 'description' => 'رسوم 2026', 'year' => 2026,
            'amount_jod' => 300, 'paid_jod' => 0, 'status' => 'unpaid',
        ]);

        $pendingOwes = Contractor::create([
            'name' => 'شركة معلّقة', 'membership_number' => '949_g', 'status' => 'pending', 'is_frozen' => false,
        ]);
        $this->seedLedger($pendingOwes);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/balances?search=947_g')->assertOk()->assertJsonPath('items.0.status', 'expired');
        $this->getJson('/api/v1/dashboard/balances?search=948_g')->assertOk()->assertJsonPath('items.0.status', 'expired');
        $this->getJson('/api/v1/dashboard/balances?search=949_g')->assertOk()->assertJsonPath('items.0.status', 'pending');
    }

    /** صفحة المقاولين بتعرض نفس "حالة العضوية" تبع صفحة الأرصدة، مش حالة الحساب الإدارية */
    public function test_contractors_list_shows_same_membership_status_as_balances(): void
    {
        $owes = $this->contractor('950_g');
        $this->seedLedger($owes); // صافي سالب
        $paid = $this->contractor('951_g');
        Membership::create([
            'contractor_id' => $paid->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => now()->startOfYear(), 'expires_at' => now()->endOfYear(),
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        foreach (['950_g' => 'expired', '951_g' => 'active'] as $number => $expected) {
            $this->getJson("/api/v1/dashboard/balances?search={$number}")->assertJsonPath('items.0.status', $expected);
            $this->getJson("/api/v1/contractors?search={$number}")->assertOk()
                ->assertJsonPath('items.0.membership_status', $expected)
                ->assertJsonPath('items.0.status', 'active');
        }
    }

    /**
     * سدّد 95% أو أكثر من مجموع ذممه وغراماته ← فعّالة رغم الباقي البسيط (حالة 9565_g: 830 من 840).
     * أقل من 95% ← منتهية. وبالحالتين الباقي بيضل عليه بالرصيد.
     */
    public function test_paying_95_percent_of_obligations_shows_active(): void
    {
        $almost = $this->contractor('9565_g');
        ContractorDue::create([
            'contractor_id' => $almost->id, 'description' => 'رسوم 2025', 'year' => 2025,
            'amount_jod' => 400, 'paid_jod' => 400, 'status' => 'paid',
        ]);
        ContractorDue::create([
            'contractor_id' => $almost->id, 'description' => 'رسوم 2026', 'year' => 2026,
            'amount_jod' => 430, 'paid_jod' => 430, 'status' => 'paid',
        ]);
        Penalty::create([
            'contractor_id' => $almost->id, 'reason' => 'غرامة تأخير',
            'amount' => 10, 'paid_amount' => 0, 'status' => 'unpaid',
        ]);

        $short = $this->contractor('9566_g');
        ContractorDue::create([
            'contractor_id' => $short->id, 'description' => 'رسوم 2026', 'year' => 2026,
            'amount_jod' => 100, 'paid_jod' => 94, 'status' => 'partially_paid',
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->getJson('/api/v1/dashboard/balances?search=9565_g')->assertOk()
            ->assertJsonPath('items.0.status', 'active')
            ->assertJsonPath('items.0.net_jod', -10);
        $this->getJson('/api/v1/contractors?search=9565_g')->assertJsonPath('items.0.membership_status', 'active');
        $this->getJson('/api/v1/dashboard/balances?search=9566_g')->assertJsonPath('items.0.status', 'expired');

        Sanctum::actingAs($almost, ['*']);
        $this->getJson('/api/v1/contractor/balance')->assertOk()
            ->assertJsonPath('items.subscription_status', 'active')
            ->assertJsonPath('items.amount_due_jod', 10);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/contractor/balance')->assertUnauthorized();
    }
}
