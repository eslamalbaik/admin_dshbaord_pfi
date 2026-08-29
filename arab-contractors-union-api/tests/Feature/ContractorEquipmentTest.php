<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Equipment;
use App\Models\EquipmentReport;
use App\Models\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '910_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    private function type(): EquipmentType
    {
        return EquipmentType::create(['name_ar' => 'حفارة']);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  index
    // ─────────────────────────────────────────────────────────────────────

    public function test_index_only_lists_own_equipment_with_pagination_structure(): void
    {
        $me      = $this->createContractor();
        $another = $this->createContractor(['membership_number' => '911_g']);
        $type    = $this->type();

        Equipment::create(['contractor_id' => $me->id, 'equipment_type_id' => $type->id, 'name' => 'حفارتي', 'daily_price' => 50]);
        Equipment::create(['contractor_id' => $another->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة غيري', 'daily_price' => 60]);

        Sanctum::actingAs($me, ['*']);

        $response = $this->getJson('/api/v1/contractor/equipment');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status', 'message', 'status_code',
                'items' => [['id', 'contractor_id', 'name', 'daily_price', 'status', 'is_hidden', 'images']],
                'meta'  => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'حفارتي');
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/v1/contractor/equipment')->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  store
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_creates_equipment_and_persists_to_database(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', [
            'equipment_type_id' => $type->id,
            'name'              => 'حفارة كبيرة',
            'daily_price'       => 120.5,
            'condition'         => 'good',
            'accept_disclaimer' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('items.name', 'حفارة كبيرة')
            ->assertJsonPath('items.contractor_id', $contractor->id);

        $this->assertDatabaseHas('equipment', [
            'contractor_id' => $contractor->id,
            'name'          => 'حفارة كبيرة',
            'status'        => 'visible',
        ]);
        $this->assertNotNull($contractor->fresh()->equipment_disclaimer_accepted_at);

        // re-fetch through API and confirm it reflects DB state
        $this->getJson('/api/v1/contractor/equipment')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'حفارة كبيرة');
    }

    public function test_store_with_images_persists_image_records(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', [
            'equipment_type_id' => $type->id,
            'name'              => 'حفارة مع صور',
            'daily_price'       => 80,
            'accept_disclaimer' => true,
            'images'            => [UploadedFile::fake()->image('e1.jpg'), UploadedFile::fake()->image('e2.jpg')],
        ]);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('items.images'));
        $this->assertTrue($response->json('items.images.0.is_primary'));
        $this->assertDatabaseCount('equipment_images', 2);
    }

    public function test_store_fails_validation_with_missing_required_fields(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['equipment_type_id', 'name', 'daily_price']);
        $this->assertDatabaseCount('equipment', 0);
    }

    public function test_store_fails_with_invalid_condition_enum(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', [
            'equipment_type_id' => $type->id,
            'name'              => 'حفارة',
            'daily_price'       => 10,
            'condition'         => 'brand_new_invalid',
            'accept_disclaimer' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['condition']);
    }

    public function test_store_rejects_nonexistent_equipment_type(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', [
            'equipment_type_id' => 999999,
            'name'              => 'حفارة',
            'daily_price'       => 10,
            'accept_disclaimer' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['equipment_type_id']);
    }

    public function test_store_requires_disclaimer_acceptance_on_first_listing(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', [
            'equipment_type_id' => $type->id,
            'name'              => 'حفارة',
            'daily_price'       => 10,
        ]);

        $response->assertStatus(403)->assertJsonPath('error', 'disclaimer_required');
        $this->assertDatabaseCount('equipment', 0);
    }

    public function test_store_blocked_when_contractor_is_banned_from_marketplace(): void
    {
        $contractor = $this->createContractor(['equipment_banned_at' => now()]);
        $type = $this->type();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', [
            'equipment_type_id' => $type->id,
            'name'              => 'حفارة',
            'daily_price'       => 10,
            'accept_disclaimer' => true,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('equipment', 0);
    }

    public function test_store_blocked_when_contractor_has_outstanding_dues(): void
    {
        $contractor = $this->createContractor();
        \App\Models\ContractorDue::create([
            'contractor_id' => $contractor->id,
            'description'   => 'ذمة غير مسددة',
            'amount_jod'    => 100,
            'status'        => 'unpaid',
        ]);
        $type = $this->type();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->postJson('/api/v1/contractor/equipment', [
            'equipment_type_id' => $type->id,
            'name'              => 'حفارة',
            'daily_price'       => 10,
            'accept_disclaimer' => true,
        ]);

        $response->assertStatus(403)->assertJsonPath('error', 'dues_pending');
        $this->assertDatabaseCount('equipment', 0);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  update
    // ─────────────────────────────────────────────────────────────────────

    public function test_update_persists_changes_to_database(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        $equipment = Equipment::create(['contractor_id' => $contractor->id, 'equipment_type_id' => $type->id, 'name' => 'قديم', 'daily_price' => 10]);
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->patchJson("/api/v1/contractor/equipment/{$equipment->id}", [
            'name'        => 'اسم محدَّث',
            'daily_price' => 99.99,
        ]);

        $response->assertStatus(200)->assertJsonPath('items.name', 'اسم محدَّث');
        $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'name' => 'اسم محدَّث']);

        // re-fetch through API confirms DB state
        $this->getJson('/api/v1/contractor/equipment')->assertJsonPath('items.0.name', 'اسم محدَّث');
    }

    public function test_update_fails_validation_on_invalid_price(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        $equipment = Equipment::create(['contractor_id' => $contractor->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة', 'daily_price' => 10]);
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->patchJson("/api/v1/contractor/equipment/{$equipment->id}", ['daily_price' => -5]);

        $response->assertStatus(422)->assertJsonValidationErrors(['daily_price']);
    }

    public function test_update_nonexistent_equipment_returns_404(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->patchJson('/api/v1/contractor/equipment/999999', ['name' => 'x'])->assertStatus(404);
    }

    public function test_update_requires_auth(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        $equipment = Equipment::create(['contractor_id' => $contractor->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة', 'daily_price' => 10]);

        $this->patchJson("/api/v1/contractor/equipment/{$equipment->id}", ['name' => 'x'])->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  destroy
    // ─────────────────────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_and_removes_from_listing(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        $equipment = Equipment::create(['contractor_id' => $contractor->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة', 'daily_price' => 10]);
        Sanctum::actingAs($contractor, ['*']);

        $this->deleteJson("/api/v1/contractor/equipment/{$equipment->id}")->assertStatus(200);

        $this->assertSoftDeleted('equipment', ['id' => $equipment->id]);
        $this->getJson('/api/v1/contractor/equipment')->assertJsonCount(0, 'items');
    }

    public function test_destroy_nonexistent_equipment_returns_404(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->deleteJson('/api/v1/contractor/equipment/999999')->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  report
    // ─────────────────────────────────────────────────────────────────────

    public function test_report_persists_report_against_reporting_contractor(): void
    {
        $owner    = $this->createContractor(['membership_number' => '912_g']);
        $reporter = $this->createContractor(['membership_number' => '913_g', 'name' => 'مبلّغ']);
        $type = $this->type();
        $equipment = Equipment::create(['contractor_id' => $owner->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة', 'daily_price' => 10]);
        Sanctum::actingAs($reporter, ['*']);

        $response = $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/report", [
            'reason' => 'السعر غير صحيح والصور مضللة',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('equipment_reports', [
            'equipment_id'  => $equipment->id,
            'contractor_id' => $reporter->id,
            'status'        => 'pending',
        ]);
    }

    public function test_report_fails_validation_without_reason(): void
    {
        $contractor = $this->createContractor();
        $type = $this->type();
        $equipment = Equipment::create(['contractor_id' => $contractor->id, 'equipment_type_id' => $type->id, 'name' => 'حفارة', 'daily_price' => 10]);
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson("/api/v1/contractor/equipment/{$equipment->id}/report", [])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);
    }

    public function test_report_nonexistent_equipment_returns_404(): void
    {
        $contractor = $this->createContractor();
        Sanctum::actingAs($contractor, ['*']);

        $this->postJson('/api/v1/contractor/equipment/999999/report', ['reason' => 'x'])->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Cross-contractor authorization (A vs B)
    // ─────────────────────────────────────────────────────────────────────

    public function test_contractor_a_cannot_update_contractor_b_equipment(): void
    {
        $a = $this->createContractor(['membership_number' => '920_g']);
        $b = $this->createContractor(['membership_number' => '921_g', 'name' => 'مقاول ب']);
        $type = $this->type();
        $bEquipment = Equipment::create(['contractor_id' => $b->id, 'equipment_type_id' => $type->id, 'name' => 'ملك ب', 'daily_price' => 20]);

        Sanctum::actingAs($a, ['*']);
        $response = $this->patchJson("/api/v1/contractor/equipment/{$bEquipment->id}", ['name' => 'استولى عليها أ']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('equipment', ['id' => $bEquipment->id, 'name' => 'ملك ب']);
    }

    public function test_contractor_a_cannot_delete_contractor_b_equipment(): void
    {
        $a = $this->createContractor(['membership_number' => '922_g']);
        $b = $this->createContractor(['membership_number' => '923_g', 'name' => 'مقاول ب']);
        $type = $this->type();
        $bEquipment = Equipment::create(['contractor_id' => $b->id, 'equipment_type_id' => $type->id, 'name' => 'ملك ب', 'daily_price' => 20]);

        Sanctum::actingAs($a, ['*']);
        $response = $this->deleteJson("/api/v1/contractor/equipment/{$bEquipment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('equipment', ['id' => $bEquipment->id, 'deleted_at' => null]);
    }

    public function test_contractor_a_cannot_see_contractor_b_equipment_in_index(): void
    {
        $a = $this->createContractor(['membership_number' => '924_g']);
        $b = $this->createContractor(['membership_number' => '925_g', 'name' => 'مقاول ب']);
        $type = $this->type();
        Equipment::create(['contractor_id' => $b->id, 'equipment_type_id' => $type->id, 'name' => 'ملك ب', 'daily_price' => 20]);

        Sanctum::actingAs($a, ['*']);
        $this->getJson('/api/v1/contractor/equipment')->assertJsonCount(0, 'items');
    }
}
