<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * قائمة سجل المدفوعات بلوحة التحكم: دفعات المقاول المحذوف بتضل باسمه ورقمه،
 * البحث بيشمل الرقم المرجعي، والملخص بيرجع عدد ومجموع كل حالة حسب الفلاتر.
 */
class PaymentsLogListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function contractor(string $name, string $number): Contractor
    {
        return Contractor::create([
            'name'              => $name,
            'membership_number' => $number,
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function payment(Contractor $c, array $attrs = []): Payment
    {
        return Payment::create(array_merge([
            'contractor_id' => $c->id,
            'amount'        => 100,
            'currency'      => 'JOD',
            'amount_jod'    => 100,
            'type'          => 'dues_payment',
            'status'        => 'pending',
            'method'        => 'bank_transfer',
            'submitted_at'  => now(),
        ], $attrs));
    }

    public function test_soft_deleted_contractor_keeps_name_and_original_number(): void
    {
        $c = $this->contractor('شركة محذوفة', '960_g');
        $this->payment($c);

        // نفس ما بيعمله ContractorController::destroy
        $c->update(['membership_number' => '960_g_deleted_' . $c->id]);
        $c->delete();

        $item = $this->getJson('/api/v1/payments/transactions')->assertOk()->json('items.0');

        $this->assertSame('شركة محذوفة', $item['contractor']);
        $this->assertSame('960_g', $item['contractor_membership_number']);
        $this->assertTrue($item['contractor_deleted']);
    }

    public function test_search_matches_transaction_number(): void
    {
        $a = $this->payment($this->contractor('شركة أ', '961_g'));
        $this->payment($this->contractor('شركة ب', '962_g'));

        $items = $this->getJson('/api/v1/payments/transactions?search=' . urlencode($a->fresh()->transaction_number))
            ->assertOk()->json('items');

        $this->assertCount(1, $items);
        $this->assertSame($a->id, $items[0]['id']);
    }

    public function test_summary_counts_each_status_ignoring_status_filter(): void
    {
        $c = $this->contractor('شركة ملخص', '963_g');
        $this->payment($c, ['status' => 'paid', 'amount_jod' => 40]);
        $this->payment($c, ['status' => 'paid', 'amount_jod' => 60]);
        $this->payment($c, ['status' => 'pending', 'amount_jod' => 25]);

        $res = $this->getJson('/api/v1/payments/transactions?status=pending')->assertOk();

        $this->assertCount(1, $res->json('items'));
        $this->assertSame(['count' => 2, 'total_jod' => 100], $res->json('summary.paid'));
        $this->assertSame(1, $res->json('summary.pending.count'));
    }
}
