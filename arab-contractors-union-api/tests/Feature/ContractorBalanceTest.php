<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorCredit;
use App\Models\ContractorDue;
use App\Models\Payment;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
    }

    public function test_contractor_with_nothing_on_record_is_settled(): void
    {
        Sanctum::actingAs($this->contractor('943_g'), ['*']);

        $this->getJson('/api/v1/contractor/balance')
            ->assertOk()
            ->assertJsonPath('items.net_jod', 0)
            ->assertJsonPath('items.position', 'settled');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/contractor/balance')->assertUnauthorized();
    }
}
