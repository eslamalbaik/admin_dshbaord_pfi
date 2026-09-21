<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Equipment;
use App\Models\EquipmentImage;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * يغطي مسار الأدمن (EquipmentController) — المسار المقابل للمقاول مغطّى في
 * ContractorEquipmentTest. الحد الأقصى 8 صور مفروض في المسارين، والاختبار هنا
 * يضمن أن سد الثغرة في أحدهما لا يترك الآخر مفتوحاً (REQ-08 #3).
 */
class EquipmentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private ?Contractor $contractor = null;

    private ?EquipmentType $type = null;

    // مُخزَّنان بالكسول: membership_number فريد، فإنشاء مقاول جديد مع كل آلية يفشل
    private function contractor(): Contractor
    {
        return $this->contractor ??= Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '915_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function type(): EquipmentType
    {
        return $this->type ??= EquipmentType::create(['name_ar' => 'حفارة']);
    }

    private function equipment(array $attrs = []): Equipment
    {
        return Equipment::create(array_merge([
            'contractor_id'     => $this->contractor()->id,
            'equipment_type_id' => $this->type()->id,
            'name'              => 'حفارة كوماتسو',
            'contract_type'     => 'daily',
            'status'            => 'visible',
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  الحد الأقصى 8 صور — إجمالي (موجودة + جديدة) لا الدفعة وحدها
    // ─────────────────────────────────────────────────────────────────────

    public function test_upload_images_rejects_when_existing_plus_new_exceeds_eight(): void
    {
        $equipment = $this->equipment();

        // 6 صور موجودة مسبقاً + 3 جديدة = 9 > 8
        foreach (range(0, 5) as $i) {
            EquipmentImage::create([
                'equipment_id' => $equipment->id,
                'path'         => "equipment/{$equipment->id}/old{$i}.jpg",
                'is_primary'   => $i === 0,
                'sort_order'   => $i,
            ]);
        }

        $this->actingAsAdmin();

        $response = $this->postJson("/api/v1/equipment/{$equipment->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('8', $response->json('message'));

        // لا شيء يُكتب عند الرفض — تبقى الست الأصلية فقط
        $this->assertSame(6, $equipment->images()->count());
    }

    public function test_upload_images_accepts_batch_that_exactly_reaches_eight(): void
    {
        $equipment = $this->equipment();

        foreach (range(0, 5) as $i) {
            EquipmentImage::create([
                'equipment_id' => $equipment->id,
                'path'         => "equipment/{$equipment->id}/old{$i}.jpg",
                'is_primary'   => $i === 0,
                'sort_order'   => $i,
            ]);
        }

        $this->actingAsAdmin();

        $this->postJson("/api/v1/equipment/{$equipment->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ])->assertStatus(201);

        $this->assertSame(8, $equipment->images()->count());
    }

    public function test_store_rejects_more_than_eight_images_in_one_request(): void
    {
        $this->actingAsAdmin();

        $images = [];
        foreach (range(1, 9) as $i)
            $images[] = UploadedFile::fake()->image("img{$i}.jpg");

        $this->postJson('/api/v1/equipment', [
            'contractor_id'     => $this->contractor()->id,
            'equipment_type_id' => $this->type()->id,
            'name'              => 'حفارة',
            'images'            => $images,
        ])->assertStatus(422)->assertJsonValidationErrors(['images']);

        $this->assertDatabaseCount('equipment', 0);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  حذف صورة — يحذف المستهدفة فقط ويرقّي التالية لرئيسية
    // ─────────────────────────────────────────────────────────────────────

    public function test_delete_image_removes_only_the_targeted_row(): void
    {
        $equipment = $this->equipment();

        $first = EquipmentImage::create([
            'equipment_id' => $equipment->id,
            'path'         => 'equipment/1/a.jpg',
            'is_primary'   => true,
            'sort_order'   => 0,
        ]);
        $second = EquipmentImage::create([
            'equipment_id' => $equipment->id,
            'path'         => 'equipment/1/b.jpg',
            'is_primary'   => false,
            'sort_order'   => 1,
        ]);

        $this->actingAsAdmin();

        $this->deleteJson("/api/v1/equipment/{$equipment->id}/images/{$first->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('equipment_images', ['id' => $first->id]);
        $this->assertDatabaseHas('equipment_images', ['id' => $second->id]);

        // الصورة المتبقية تصير الرئيسية تلقائياً
        $this->assertTrue((bool) $second->fresh()->is_primary);
    }

    public function test_delete_image_rejects_mismatched_equipment_and_image_pair(): void
    {
        $equipmentA = $this->equipment();
        $equipmentB = $this->equipment(['name' => 'رافعة']);

        $imageOfB = EquipmentImage::create([
            'equipment_id' => $equipmentB->id,
            'path'         => 'equipment/2/a.jpg',
            'is_primary'   => true,
            'sort_order'   => 0,
        ]);

        $this->actingAsAdmin();

        $this->deleteJson("/api/v1/equipment/{$equipmentA->id}/images/{$imageOfB->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('equipment_images', ['id' => $imageOfB->id]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  update — نوع المعدة والوصف
    // ─────────────────────────────────────────────────────────────────────

    public function test_update_persists_equipment_type_change(): void
    {
        $equipment = $this->equipment();
        $newType   = EquipmentType::create(['name_ar' => 'رافعة']);

        $this->actingAsAdmin();

        $this->patchJson("/api/v1/equipment/{$equipment->id}", [
            'equipment_type_id' => $newType->id,
        ])->assertStatus(200)
          ->assertJsonPath('equipment_type_id', $newType->id);

        $this->assertDatabaseHas('equipment', [
            'id'                => $equipment->id,
            'equipment_type_id' => $newType->id,
        ]);
    }

    public function test_update_rejects_nonexistent_equipment_type(): void
    {
        $equipment = $this->equipment();

        $this->actingAsAdmin();

        $this->patchJson("/api/v1/equipment/{$equipment->id}", [
            'equipment_type_id' => 99999,
        ])->assertStatus(422)->assertJsonValidationErrors(['equipment_type_id']);
    }

    public function test_update_rejects_description_exceeding_max_length(): void
    {
        $equipment = $this->equipment();

        $this->actingAsAdmin();

        $this->patchJson("/api/v1/equipment/{$equipment->id}", [
            'description' => str_repeat('أ', 2001),
        ])->assertStatus(422)->assertJsonValidationErrors(['description']);
    }

    public function test_store_rejects_description_exceeding_max_length(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/equipment', [
            'contractor_id'     => $this->contractor()->id,
            'equipment_type_id' => $this->type()->id,
            'name'              => 'حفارة',
            'description'       => str_repeat('أ', 2001),
        ])->assertStatus(422)->assertJsonValidationErrors(['description']);

        $this->assertDatabaseCount('equipment', 0);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  auth
    // ─────────────────────────────────────────────────────────────────────

    public function test_admin_equipment_routes_require_auth(): void
    {
        $equipment = $this->equipment();

        $this->postJson('/api/v1/equipment', [])->assertStatus(401);
        $this->patchJson("/api/v1/equipment/{$equipment->id}", [])->assertStatus(401);
        $this->postJson("/api/v1/equipment/{$equipment->id}/images", [])->assertStatus(401);
    }
}
