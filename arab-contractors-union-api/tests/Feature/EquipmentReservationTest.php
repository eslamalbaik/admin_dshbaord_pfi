<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Equipment;
use App\Models\EquipmentBlockedDate;
use App\Models\EquipmentReservation;
use App\Models\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * حجوزات فعلية للآليات (REQ-08 #6) — طلب من التطبيق، قبول أوتوماتيكي فوري إن كانت
 * الفترة متاحة، بدون خطوة موافقة من المالك/الإدارة (قرار منتجي).
 */
class EquipmentReservationTest extends TestCase
{
    use RefreshDatabase;

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '940_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    private function equipment(Contractor $owner): Equipment
    {
        $type = EquipmentType::create(['name_ar' => 'حفارة']);

        return Equipment::create([
            'contractor_id'     => $owner->id,
            'equipment_type_id' => $type->id,
            'name'              => 'حفارة للإيجار',
            'status'            => 'visible',
        ]);
    }

    public function test_reservation_request_auto_confirms_when_available(): void
    {
        $owner   = $this->createContractor();
        $renter  = $this->createContractor(['membership_number' => '941_g']);
        $equipment = $this->equipment($owner);
        Sanctum::actingAs($renter, ['*']);

        $response = $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/reservations", [
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date'   => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(201)->assertJsonPath('items.status', 'confirmed');
        $this->assertDatabaseHas('equipment_reservations', [
            'equipment_id'  => $equipment->id,
            'contractor_id' => $renter->id,
            'status'        => 'confirmed',
        ]);
    }

    public function test_reservation_rejected_when_overlaps_existing_confirmed_reservation(): void
    {
        $owner  = $this->createContractor();
        $first  = $this->createContractor(['membership_number' => '942_g']);
        $second = $this->createContractor(['membership_number' => '943_g']);
        $equipment = $this->equipment($owner);

        EquipmentReservation::create([
            'equipment_id'  => $equipment->id,
            'contractor_id' => $first->id,
            'start_date'    => now()->addDays(5)->toDateString(),
            'end_date'      => now()->addDays(10)->toDateString(),
            'status'        => 'confirmed',
        ]);

        Sanctum::actingAs($second, ['*']);
        $response = $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/reservations", [
            // يتقاطع جزئياً مع 5-10 أعلاه
            'start_date' => now()->addDays(8)->toDateString(),
            'end_date'   => now()->addDays(12)->toDateString(),
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'dates_unavailable');
        $this->assertDatabaseCount('equipment_reservations', 1);
    }

    public function test_reservation_rejected_when_overlaps_admin_blocked_date(): void
    {
        $owner  = $this->createContractor();
        $renter = $this->createContractor(['membership_number' => '944_g']);
        $equipment = $this->equipment($owner);

        EquipmentBlockedDate::create([
            'equipment_id' => $equipment->id,
            'blocked_date' => now()->addDays(6)->toDateString(),
            'reason'       => 'maintenance',
        ]);

        Sanctum::actingAs($renter, ['*']);
        $response = $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/reservations", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'dates_unavailable');
    }

    public function test_reservation_allowed_after_a_cancelled_reservation_freed_the_dates(): void
    {
        $owner  = $this->createContractor();
        $first  = $this->createContractor(['membership_number' => '945_g']);
        $second = $this->createContractor(['membership_number' => '946_g']);
        $equipment = $this->equipment($owner);

        $existing = EquipmentReservation::create([
            'equipment_id'  => $equipment->id,
            'contractor_id' => $first->id,
            'start_date'    => now()->addDays(5)->toDateString(),
            'end_date'      => now()->addDays(10)->toDateString(),
            'status'        => 'cancelled',
        ]);

        Sanctum::actingAs($second, ['*']);
        $response = $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/reservations", [
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date'   => now()->addDays(10)->toDateString(),
        ]);

        $response->assertStatus(201);
    }

    public function test_cannot_reserve_own_equipment(): void
    {
        $owner = $this->createContractor();
        $equipment = $this->equipment($owner);
        Sanctum::actingAs($owner, ['*']);

        $response = $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/reservations", [
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date'   => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('equipment_reservations', 0);
    }

    public function test_cannot_reserve_hidden_equipment(): void
    {
        $owner = $this->createContractor();
        $renter = $this->createContractor(['membership_number' => '947_g']);
        $equipment = $this->equipment($owner);
        $equipment->update(['is_hidden' => true]);

        Sanctum::actingAs($renter, ['*']);
        $response = $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/reservations", [
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date'   => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_renter_can_cancel_own_upcoming_reservation(): void
    {
        $owner  = $this->createContractor();
        $renter = $this->createContractor(['membership_number' => '948_g']);
        $equipment = $this->equipment($owner);

        $reservation = EquipmentReservation::create([
            'equipment_id'  => $equipment->id,
            'contractor_id' => $renter->id,
            'start_date'    => now()->addDays(5)->toDateString(),
            'end_date'      => now()->addDays(10)->toDateString(),
            'status'        => 'confirmed',
        ]);

        Sanctum::actingAs($renter, ['*']);
        $response = $this->deleteJson("/api/v1/contractor/equipment-reservations/{$reservation->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('equipment_reservations', ['id' => $reservation->id, 'status' => 'cancelled']);
    }

    public function test_cannot_cancel_someone_elses_reservation(): void
    {
        $owner   = $this->createContractor();
        $renter  = $this->createContractor(['membership_number' => '949_g']);
        $intruder = $this->createContractor(['membership_number' => '950_g']);
        $equipment = $this->equipment($owner);

        $reservation = EquipmentReservation::create([
            'equipment_id'  => $equipment->id,
            'contractor_id' => $renter->id,
            'start_date'    => now()->addDays(5)->toDateString(),
            'end_date'      => now()->addDays(10)->toDateString(),
            'status'        => 'confirmed',
        ]);

        Sanctum::actingAs($intruder, ['*']);
        $response = $this->deleteJson("/api/v1/contractor/equipment-reservations/{$reservation->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('equipment_reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
    }

    public function test_my_reservations_only_lists_own_reservations_as_renter(): void
    {
        $owner  = $this->createContractor();
        $renter = $this->createContractor(['membership_number' => '951_g']);
        $other  = $this->createContractor(['membership_number' => '952_g']);
        $equipment = $this->equipment($owner);

        EquipmentReservation::create([
            'equipment_id' => $equipment->id, 'contractor_id' => $renter->id,
            'start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(6)->toDateString(),
            'status' => 'confirmed',
        ]);
        EquipmentReservation::create([
            'equipment_id' => $equipment->id, 'contractor_id' => $other->id,
            'start_date' => now()->addDays(8)->toDateString(), 'end_date' => now()->addDays(9)->toDateString(),
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($renter, ['*']);
        $this->getJson('/api/v1/contractor/my-equipment-reservations')->assertJsonCount(1, 'items');
    }
}
