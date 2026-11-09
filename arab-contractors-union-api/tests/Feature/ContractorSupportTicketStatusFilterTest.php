<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /contractor/support-tickets?status=... — فلترة تذاكر المقاول حسب الحالة
 * (open, in_progress, answered, closed).
 */
class ContractorSupportTicketStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $num): Contractor
    {
        return Contractor::create([
            'name'              => 'شركة ' . $num,
            'membership_number' => $num,
            'phone'             => '05900' . rand(10000, 99999),
            'password'          => Hash::make('Test@1234'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ]);
    }

    private function ticket(Contractor $c, string $status): SupportTicket
    {
        return SupportTicket::create([
            'contractor_id' => $c->id,
            'subject'       => 'تذكرة ' . $status,
            'category'      => 'technical',
            'message'       => 'نص',
            'status'        => $status,
        ]);
    }

    public function test_filters_my_tickets_by_status(): void
    {
        $me    = $this->contractor('961_g');
        $other = $this->contractor('962_g');

        foreach (['open', 'in_progress', 'answered', 'closed', 'closed'] as $s) {
            $this->ticket($me, $s);
        }
        $this->ticket($other, 'closed');

        Sanctum::actingAs($me, ['*']);
        app('auth')->guard('contractor')->setUser($me);

        $this->getJson('/api/v1/contractor/support-tickets?per_page=15')
            ->assertOk()->assertJsonCount(5, 'items');

        $closed = $this->getJson('/api/v1/contractor/support-tickets?per_page=15&status=closed')
            ->assertOk()->assertJsonCount(2, 'items')->json('items');
        $this->assertSame(['closed'], array_values(array_unique(array_column($closed, 'status'))));

        foreach (['open', 'in_progress', 'answered'] as $s) {
            $this->getJson('/api/v1/contractor/support-tickets?status=' . $s)
                ->assertOk()->assertJsonCount(1, 'items')
                ->assertJsonPath('items.0.status', $s);
        }
    }

    public function test_rejects_unknown_status(): void
    {
        $me = $this->contractor('963_g');
        Sanctum::actingAs($me, ['*']);
        app('auth')->guard('contractor')->setUser($me);

        $this->getJson('/api/v1/contractor/support-tickets?status=close')
            ->assertStatus(422);
    }
}
