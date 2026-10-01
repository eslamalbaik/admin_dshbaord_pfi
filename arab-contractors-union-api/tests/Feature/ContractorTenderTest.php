<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Tender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorTenderTest extends TestCase
{
    use RefreshDatabase;

    private function createContractor(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '990_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ], $attrs));
    }

    // ─────────────────────────────────────────────────────────────────────
    //  reference_number auto-generation
    // ─────────────────────────────────────────────────────────────────────

    public function test_store_auto_generates_reference_number(): void
    {
        $tender = Tender::create(['title' => 'عطاء اختباري', 'status' => 'open']);
        $tender->update(['reference_number' => 'TND-' . $tender->created_at->year . '-' . str_pad((string) $tender->id, 3, '0', STR_PAD_LEFT)]);

        $this->assertMatchesRegularExpression('/^TND-\d{4}-\d{3,}$/', $tender->fresh()->reference_number);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  computed badges: is_new / is_updated / closing_soon
    // ─────────────────────────────────────────────────────────────────────

    public function test_is_new_true_within_48_hours_of_creation(): void
    {
        $fresh = Tender::create(['title' => 'عطاء جديد', 'status' => 'open']);
        $this->assertTrue($fresh->fresh()->is_new);

        $old = Tender::create(['title' => 'عطاء قديم', 'status' => 'open']);
        $old->created_at = now()->subDays(5);
        $old->save();
        $this->assertFalse($old->fresh()->is_new);
    }

    public function test_is_updated_true_only_when_modified_after_creation_and_no_longer_new(): void
    {
        $tender = Tender::create(['title' => 'عطاء', 'status' => 'open']);
        $tender->created_at = now()->subDays(5);
        $tender->updated_at = now()->subDays(5);
        $tender->save();
        $this->assertFalse($tender->fresh()->is_updated);

        $tender->title = 'عطاء معدَّل';
        $tender->save(); // updated_at يصير الآن
        $this->assertTrue($tender->fresh()->is_updated);
        $this->assertFalse($tender->fresh()->is_new); // created_at لسا قديم
    }

    public function test_closing_soon_true_when_deadline_within_seven_days(): void
    {
        $soon = Tender::create(['title' => 'عطاء قريب', 'status' => 'open', 'deadline' => now()->addDays(3)]);
        $this->assertTrue($soon->fresh()->closing_soon);

        $far = Tender::create(['title' => 'عطاء بعيد', 'status' => 'open', 'deadline' => now()->addDays(30)]);
        $this->assertFalse($far->fresh()->closing_soon);

        $expired = Tender::create(['title' => 'عطاء منتهي', 'status' => 'closed', 'deadline' => now()->subDays(1)]);
        $this->assertFalse($expired->fresh()->closing_soon);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  union_notes visibility: public vs contractor
    // ─────────────────────────────────────────────────────────────────────

    public function test_union_notes_hidden_from_public_tenders_endpoint(): void
    {
        Tender::create(['title' => 'عطاء', 'status' => 'open', 'union_notes' => 'ملاحظة داخلية للمقاولين']);

        $response = $this->getJson('/api/v1/tenders-public');

        $response->assertStatus(200)->assertJsonPath('items.0.union_notes', null);
    }

    public function test_union_notes_visible_to_authenticated_contractor(): void
    {
        $contractor = $this->createContractor();
        Tender::create(['title' => 'عطاء', 'status' => 'open', 'union_notes' => 'ملاحظة داخلية للمقاولين']);
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->getJson('/api/v1/contractor/tenders');

        $response->assertStatus(200)->assertJsonPath('items.0.union_notes', 'ملاحظة داخلية للمقاولين');
    }

    public function test_issuing_entity_and_reference_number_exposed_to_contractor(): void
    {
        $contractor = $this->createContractor();
        $tender = Tender::create([
            'title' => 'عطاء', 'status' => 'open',
            'issuing_entity' => 'بلدية رام الله', 'reference_number' => 'TND-2026-001',
        ]);
        Sanctum::actingAs($contractor, ['*']);

        $this->getJson('/api/v1/contractor/tenders')
            ->assertJsonPath('items.0.issuing_entity', 'بلدية رام الله')
            ->assertJsonPath('items.0.reference_number', 'TND-2026-001');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  filters: deadline range + states
    // ─────────────────────────────────────────────────────────────────────

    public function test_deadline_range_filter(): void
    {
        $contractor = $this->createContractor();
        Tender::create(['title' => 'ضمن المدى', 'status' => 'open', 'deadline' => now()->addDays(5)]);
        Tender::create(['title' => 'خارج المدى', 'status' => 'open', 'deadline' => now()->addDays(60)]);
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->getJson('/api/v1/contractor/tenders?deadline_from=' . now()->toDateString() . '&deadline_to=' . now()->addDays(10)->toDateString());

        $response->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'ضمن المدى');
    }

    public function test_states_filter_closing_soon(): void
    {
        $contractor = $this->createContractor();
        Tender::create(['title' => 'قريب الانتهاء', 'status' => 'open', 'deadline' => now()->addDays(2)]);
        Tender::create(['title' => 'بعيد', 'status' => 'open', 'deadline' => now()->addDays(60)]);
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->getJson('/api/v1/contractor/tenders?states[]=closing_soon');

        $response->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'قريب الانتهاء');
    }

    public function test_states_filter_new(): void
    {
        $contractor = $this->createContractor();
        Tender::create(['title' => 'جديد', 'status' => 'open']);
        $old = Tender::create(['title' => 'قديم', 'status' => 'open']);
        $old->created_at = now()->subDays(10);
        $old->save();
        Sanctum::actingAs($contractor, ['*']);

        $response = $this->getJson('/api/v1/contractor/tenders?states[]=new');

        $response->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'جديد');
    }
}
