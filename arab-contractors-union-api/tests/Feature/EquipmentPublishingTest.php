<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ملاحظات سوق الآليات (REQ-08 #13، #16، #17، #21): رقم واتساب المالك بمقدمة 970/972،
 * صورة الغلاف المختارة بالفورم، النشر المجدول يؤجل الظهور بالسوق، والنوع الملغى "غير محدد".
 */
class EquipmentPublishingTest extends TestCase
{
    use RefreshDatabase;

    private ?Contractor $owner = null;

    private ?EquipmentType $type = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    private function owner(): Contractor
    {
        return $this->owner ??= Contractor::create([
            'name'              => 'مالك الآلية',
            'membership_number' => '930_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function viewer(): Contractor
    {
        return Contractor::create([
            'name'              => 'مقاول يتصفح السوق',
            'membership_number' => '931_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
    }

    private function type(): EquipmentType
    {
        return $this->type ??= EquipmentType::create(['name_ar' => 'حفارة']);
    }

    private function payload(array $attrs = []): array
    {
        return array_merge([
            'contractor_id'     => $this->owner()->id,
            'equipment_type_id' => $this->type()->id,
            'name'              => 'حفارة كوماتسو',
        ], $attrs);
    }

    // ─── رقم واتساب المالك ───────────────────────────────────────────────

    public function test_owner_phone_is_normalized_to_international_digits(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/equipment', $this->payload(['owner_phone' => '+970 599-123456']))
            ->assertStatus(201)
            ->assertJsonPath('owner_phone', '970599123456');
    }

    public function test_owner_phone_without_970_or_972_prefix_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/equipment', $this->payload(['owner_phone' => '0599123456']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['owner_phone']);

        $this->postJson('/api/v1/equipment', $this->payload(['owner_phone' => '962791234567']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['owner_phone']);
    }

    // ─── صورة الغلاف ──────────────────────────────────────────────────────

    public function test_store_marks_the_chosen_cover_image_as_primary(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/api/v1/equipment', $this->payload([
            'images'        => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
            'primary_index' => 2,
        ]), ['Accept' => 'application/json'])->assertStatus(201);

        $images = Equipment::findOrFail($response->json('id'))->images;
        $this->assertSame([false, false, true], $images->pluck('is_primary')->map(fn ($v) => (bool) $v)->all());
    }

    // ─── النشر المجدول ───────────────────────────────────────────────────

    public function test_scheduled_publish_date_must_start_from_tomorrow(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/equipment', $this->payload([
            'publish_mode' => 'schedule',
            'published_at' => now()->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors(['published_at']);

        $this->postJson('/api/v1/equipment', $this->payload(['publish_mode' => 'schedule']))
            ->assertStatus(422)->assertJsonValidationErrors(['published_at']);
    }

    public function test_scheduled_equipment_stays_out_of_the_marketplace_until_its_date(): void
    {
        $this->actingAsAdmin();

        $id = $this->postJson('/api/v1/equipment', $this->payload([
            'publish_mode' => 'schedule',
            'published_at' => now()->addDays(2)->toDateString(),
        ]))->assertStatus(201)->json('id');

        Sanctum::actingAs($this->viewer(), ['*']);
        $this->getJson('/api/v1/contractor/equipment/marketplace')
            ->assertStatus(200)
            ->assertJsonCount(0, 'items');

        $this->travel(3)->days();

        $this->getJson('/api/v1/contractor/equipment/marketplace')
            ->assertStatus(200)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $id);
    }

    public function test_publishing_now_makes_a_scheduled_equipment_visible_immediately(): void
    {
        $equipment = Equipment::create($this->payload([
            'status'       => 'visible',
            'published_at' => now()->addDays(5),
        ]));

        $this->actingAsAdmin();
        $this->patchJson("/api/v1/equipment/{$equipment->id}", ['publish_mode' => 'now'])->assertStatus(200);

        $this->assertFalse($equipment->fresh()->is_scheduled);
        $this->assertSame(1, Equipment::visibleInMarketplace()->count());
    }

    public function test_quick_status_change_keeps_the_scheduled_date(): void
    {
        $date = now()->addDays(5)->startOfDay();
        $equipment = Equipment::create($this->payload(['status' => 'hidden', 'published_at' => $date]));

        $this->actingAsAdmin();
        $this->patchJson("/api/v1/equipment/{$equipment->id}", ['status' => 'visible'])->assertStatus(200);

        $this->assertTrue($equipment->fresh()->published_at->equalTo($date));
    }

    // ─── مميزة + موقوفة ──────────────────────────────────────────────────

    public function test_suspending_equipment_turns_off_featured(): void
    {
        $equipment = Equipment::create($this->payload(['status' => 'visible', 'is_featured' => true]));

        $this->actingAsAdmin();
        $this->patchJson("/api/v1/equipment/{$equipment->id}", ['status' => 'suspended'])
            ->assertStatus(200)
            ->assertJsonPath('is_featured', false);

        // وما بيصير يتفعّل وهي لسا موقوفة
        $this->patchJson("/api/v1/equipment/{$equipment->id}", ['is_featured' => true])
            ->assertStatus(200)
            ->assertJsonPath('is_featured', false);
    }

    public function test_store_hidden_by_owner_cannot_be_featured(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/equipment', $this->payload(['status' => 'hidden', 'is_featured' => true]))
            ->assertStatus(201)
            ->assertJsonPath('is_featured', false);
    }

    // ─── نوع آلية ملغى ───────────────────────────────────────────────────

    public function test_equipment_of_a_deactivated_type_shows_type_as_undefined(): void
    {
        $equipment = Equipment::create($this->payload(['status' => 'visible']));
        $this->type()->update(['is_active' => false]);

        Sanctum::actingAs($this->viewer(), ['*']);

        $this->getJson("/api/v1/contractor/equipment/marketplace/{$equipment->id}")
            ->assertStatus(200)
            ->assertJsonPath('items.type.name_ar', 'غير محدد');
    }

    public function test_equipment_of_an_active_type_keeps_its_type_name(): void
    {
        $equipment = Equipment::create($this->payload(['status' => 'visible']));

        Sanctum::actingAs($this->viewer(), ['*']);

        $this->getJson("/api/v1/contractor/equipment/marketplace/{$equipment->id}")
            ->assertStatus(200)
            ->assertJsonPath('items.type.name_ar', 'حفارة');
    }
}
