<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Tender;
use App\Models\TenderCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorTenderCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsContractor(): Contractor
    {
        $contractor = Contractor::create([
            'name'              => 'شركة اختبار للمقاولات',
            'membership_number' => '991_g',
            'status'            => 'active',
            'is_frozen'         => false,
        ]);
        Sanctum::actingAs($contractor, ['*']);

        return $contractor;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/contractor/tenders/categories')->assertStatus(401);
    }

    public function test_returns_active_categories_in_order_with_open_counts(): void
    {
        $this->actingAsContractor();

        TenderCategory::where('name', 'طرق')->update(['is_active' => false]);

        Tender::create(['title' => 'مفتوح 1', 'status' => 'open', 'category' => 'مباني']);
        Tender::create(['title' => 'مفتوح 2', 'status' => 'open', 'category' => 'مباني']);
        Tender::create(['title' => 'مغلق', 'status' => 'closed', 'category' => 'مباني']);
        $archived = Tender::create(['title' => 'مؤرشف', 'status' => 'open', 'category' => 'مباني']);
        $archived->forceFill(['archived_at' => now()])->saveQuietly();

        $items = $this->getJson('/api/v1/contractor/tenders/categories')
            ->assertOk()
            ->json('items');

        $names = array_column($items, 'name');
        $this->assertNotContains('طرق', $names);
        $this->assertSame(
            TenderCategory::where('is_active', true)->ordered()->pluck('name')->all(),
            $names,
        );

        $buildings = collect($items)->firstWhere('name', 'مباني');
        $this->assertSame(2, $buildings['open_tenders_count']);
        $this->assertSame(3, $buildings['active_tenders_count']);
        $this->assertSame('مباني', $buildings['value']);
        $this->assertArrayHasKey('image_url', $buildings);
        $this->assertArrayHasKey('sort_order', $buildings);
    }

    public function test_tenders_list_filters_by_category_name_and_id(): void
    {
        $this->actingAsContractor();

        Tender::create(['title' => 'مبنى', 'status' => 'open', 'category' => 'مباني']);
        Tender::create(['title' => 'شارع', 'status' => 'open', 'category' => 'طرق']);

        $this->getJson('/api/v1/contractor/tenders?category=' . urlencode('طرق'))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'شارع');

        $id = TenderCategory::where('name', 'مباني')->value('id');
        $this->getJson("/api/v1/contractor/tenders?category_id={$id}")
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'مبنى');

        $this->getJson('/api/v1/contractor/tenders?category_id=999999')
            ->assertOk()
            ->assertJsonCount(0, 'items');
    }
}
