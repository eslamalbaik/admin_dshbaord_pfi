<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * eslam 31/10: مقاول منتهي (آخر عضوية 2025) انضافتله ذمم السنة وانسدّت ← الأرصدة وقائمة
 * المقاولين عرضوه "فعّالة"، بس التطبيق ضل "منتهية" ونافذة تغيير الحالة قالت "من منتهي"،
 * لأن الذمة ما فيها كلمة "اشتراك/عضوية" فما انعدّت رسوم اشتراك وما تغيّر contractors.status.
 */
class MembershipStatusSyncWithBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Contractor $contractor;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        Queue::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->contractor = Contractor::create([
            'name'              => 'شركة منتهية 2025',
            'membership_number' => '988_g',
            'status'            => 'expired',
            'is_frozen'         => false,
        ]);
        Membership::create([
            'contractor_id' => $this->contractor->id,
            'type'          => 'new',
            'status'        => 'expired',
            'starts_at'     => '2025-01-01',
            'expires_at'    => '2025-12-31',
        ]);
    }

    private function due(float $amount, string $description = 'ذمم السنة'): ContractorDue
    {
        return ContractorDue::create([
            'contractor_id' => $this->contractor->id,
            'year'          => now()->year,
            'description'   => $description,
            'amount_jod'    => $amount,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ]);
    }

    private function manualPayment(float $amount): int
    {
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson('/api/v1/payments/transactions/manual', [
            'contractor_id' => $this->contractor->id, 'amount' => $amount, 'currency' => 'JOD', 'method' => 'cash',
        ])->assertSuccessful();

        return (int) \App\Models\Payment::where('contractor_id', $this->contractor->id)->latest('id')->value('id');
    }

    private function appHomeBadge(): ?string
    {
        Sanctum::actingAs($this->contractor->fresh(), ['*']);

        return $this->getJson('/api/v1/contractor/home')->assertOk()->json('items.membership.badge');
    }

    private function listRow(): array
    {
        Sanctum::actingAs($this->admin, ['*']);

        return collect($this->getJson('/api/v1/contractors?per_page=50')->assertOk()->json('items'))
            ->firstWhere('id', $this->contractor->id);
    }

    public function test_paying_current_year_dues_makes_app_and_dashboard_agree_on_active(): void
    {
        $this->due(150);
        $this->assertSame('expired', $this->appHomeBadge());

        $this->manualPayment(150);

        $contractor = $this->contractor->fresh();
        $this->assertSame('active', $contractor->status);
        $this->assertSame('active', $this->appHomeBadge());

        Sanctum::actingAs($contractor, ['*']);
        $this->getJson('/api/v1/contractor/balance')->assertJsonPath('items.subscription_status', 'active')
            ->assertJsonPath('items.membership_expires_at', now()->year . '-12-31');

        // القائمة: الحالة المحفوظة (اللي بتبدأ منها نافذة التغيير) = المعروضة
        $row = $this->listRow();
        $this->assertSame('active', $row['status']);
        $this->assertSame('active', $row['membership_status']);

        $this->assertSame(1, Membership::where('contractor_id', $contractor->id)
            ->where('status', 'active')->whereYear('expires_at', now()->year)->count());
    }

    public function test_partial_payment_below_95_percent_keeps_expired(): void
    {
        $this->due(300);
        $this->manualPayment(200);

        $this->assertSame('expired', $this->contractor->fresh()->status);
        $this->assertSame('expired', $this->appHomeBadge());
    }

    public function test_paying_95_percent_activates_and_keeps_the_rest_owed(): void
    {
        $this->due(300);
        $this->manualPayment(285);

        $this->assertSame('active', $this->contractor->fresh()->status);
        $this->assertSame('active', $this->appHomeBadge());
        $this->getJson('/api/v1/contractor/balance')->assertJsonPath('items.amount_due_jod', 15);
    }

    public function test_paying_just_under_95_percent_stays_expired(): void
    {
        $this->due(300);
        $this->manualPayment(284.99);

        $this->assertSame('expired', $this->contractor->fresh()->status);
    }

    public function test_reverting_the_payment_returns_to_expired(): void
    {
        $this->due(150);
        $paymentId = $this->manualPayment(150);
        $this->assertSame('active', $this->contractor->fresh()->status);

        $this->postJson("/api/v1/payments/transactions/{$paymentId}/status", [
            'status' => 'pending', 'reason' => 'اختبار الترجيع',
        ])->assertOk();

        $this->assertSame('expired', $this->contractor->fresh()->status);
        $this->assertSame(0, Membership::where('contractor_id', $this->contractor->id)
            ->where('status', 'active')->count());
        $this->assertSame('expired', $this->appHomeBadge());
    }

    public function test_expired_contractor_without_a_current_year_due_stays_expired(): void
    {
        ContractorDue::create([
            'contractor_id' => $this->contractor->id, 'year' => now()->year - 1, 'description' => 'ذمة قديمة',
            'amount_jod' => 100, 'paid_jod' => 0, 'status' => 'unpaid', 'source' => 'manual',
        ]);
        $this->manualPayment(100);

        $this->assertSame('expired', $this->contractor->fresh()->status);
    }

    public function test_fee_due_still_creates_a_single_membership(): void
    {
        $this->due(300, 'رسوم اشتراك ' . now()->year);
        $this->manualPayment(300);

        $this->assertSame(1, Membership::where('contractor_id', $this->contractor->id)
            ->where('status', 'active')->whereYear('expires_at', now()->year)->count());
        $this->assertSame('active', $this->contractor->fresh()->status);
    }
}
