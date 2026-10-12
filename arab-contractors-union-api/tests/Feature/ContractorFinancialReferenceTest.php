<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Penalty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorFinancialReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(): Contractor
    {
        $c = Contractor::create([
            'name' => 'شركة المرجع', 'membership_number' => '960_g', 'status' => 'active', 'is_frozen' => false,
        ]);
        Sanctum::actingAs($c, ['*']);

        return $c;
    }

    private function due(Contractor $c, int $year, float $amount = 100): ContractorDue
    {
        $due = ContractorDue::create([
            'contractor_id' => $c->id, 'description' => 'رسوم', 'year' => $year,
            'amount_jod' => $amount, 'paid_jod' => 0, 'status' => 'unpaid',
        ]);
        $due->update(['reference_number' => ContractorDue::generateReferenceNumber($due)]);

        return $due;
    }

    public function test_new_penalty_gets_a_reference_number_shown_on_its_card(): void
    {
        $c = $this->contractor();
        $penalty = Penalty::create(['contractor_id' => $c->id, 'reason' => 'غرامة تأخير', 'amount' => 100, 'status' => 'unpaid']);

        $ref = 'PEN-' . now()->year . '-' . str_pad((string) $penalty->id, 3, '0', STR_PAD_LEFT);
        $this->assertSame($ref, $penalty->fresh()->reference_number);

        $this->getJson('/api/v1/contractor/financial')
            ->assertOk()
            ->assertJsonPath('items.penalties.0.reference_number', $ref);
    }

    public function test_pending_transfer_without_a_typed_reference_falls_back(): void
    {
        $c = $this->contractor();
        $due = $this->due($c, now()->year);

        $linked = Payment::create([
            'contractor_id' => $c->id, 'contractor_due_id' => $due->id, 'amount' => 44, 'currency' => 'ILS',
            'type' => 'dues_payment', 'status' => 'pending', 'method' => 'bank_transfer', 'submitted_at' => now(),
        ]);
        $unlinked = Payment::create([
            'contractor_id' => $c->id, 'amount' => 10, 'currency' => 'JOD',
            'type' => 'dues_payment', 'status' => 'pending', 'method' => 'bank_transfer', 'submitted_at' => now(),
        ]);

        $res = $this->getJson('/api/v1/contractor/financial')->assertOk();
        $refs = collect($res->json('items.pending_dues_payments'))->pluck('reference_number', 'id');
        $this->assertSame($due->reference_number, $refs[$linked->id]);
        $this->assertSame($unlinked->fresh()->transaction_number, $refs[$unlinked->id]);
        $res->assertJsonPath('items.dues.0.pending_payment.reference_number', $due->reference_number);

        $this->getJson("/api/v1/contractor/payments/{$unlinked->id}")
            ->assertOk()
            ->assertJsonPath('items.reference_number', $unlinked->fresh()->transaction_number);
    }

    public function test_year_dues_returns_current_and_later_years_by_default(): void
    {
        $c = $this->contractor();
        $this->due($c, now()->year - 1);
        $current = $this->due($c, now()->year, 200);
        $next = $this->due($c, now()->year + 1, 150);

        $this->getJson('/api/v1/contractor/financial/dues')
            ->assertOk()
            ->assertJsonPath('items.total_jod', '350.00')
            ->assertJsonPath('items.dues.0.id', $current->id)
            ->assertJsonPath('items.dues.1.id', $next->id)
            ->assertJsonCount(2, 'items.dues');

        $this->getJson('/api/v1/contractor/financial/dues?year=' . (now()->year - 1))
            ->assertOk()
            ->assertJsonCount(1, 'items.dues');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formats')]
    public function test_event_display_flags_follow_the_attendance_format(?string $format, bool $location, bool $stream): void
    {
        $this->contractor();
        $event = Event::create([
            'title' => 'فعالية', 'slug' => 'event-' . ($format ?? 'none'), 'body' => 'نص', 'is_published' => true,
            'published_at' => now()->subDay(), 'event_date' => now()->addDays(3),
            'event_format' => $format, 'event_location' => 'غزة', 'stream_url' => 'https://zoom.us/j/1',
        ]);

        $this->getJson("/api/v1/contractor/events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('items.show_location', $location)
            ->assertJsonPath('items.show_stream_url', $stream);
    }

    public static function formats(): array
    {
        return [
            'onsite' => ['onsite', true, false],
            'online' => ['online', false, true],
            'hybrid' => ['hybrid', true, true],
            'none'   => [null, true, true],
        ];
    }
}
