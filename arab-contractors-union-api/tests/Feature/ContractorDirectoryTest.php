<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function contractor(string $name, string $number, string $status = 'active', array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => $name,
            'membership_number' => $number,
            'status'            => $status,
            'is_frozen'         => false,
            'phone'             => '0599000000',
            'email'             => $number . '@example.com',
        ], $attrs));
    }

    public function test_search_by_name_returns_public_fields_only(): void
    {
        $this->contractor('شركة البناء الحديث', '901_g', 'active', ['city' => 'غزة']);
        $this->contractor('شركة الأمل للمقاولات', '902_g');

        $res = $this->getJson('/api/v1/contractors-directory?search=البناء')->assertOk();

        $res->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'شركة البناء الحديث')
            ->assertJsonPath('items.0.membership_number', '901_g')
            ->assertJsonPath('items.0.city', 'غزة');

        $item = $res->json('items.0');
        $this->assertArrayNotHasKey('phone', $item);
        $this->assertArrayNotHasKey('email', $item);
        $this->assertArrayNotHasKey('id', $item);
    }

    public function test_search_by_membership_number_with_or_without_suffix(): void
    {
        $this->contractor('شركة الرقم', '184_g');
        $this->contractor('شركة أخرى', '1840_g');

        $this->getJson('/api/v1/contractors-directory?search=184')
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.membership_number', '184_g');
        $this->getJson('/api/v1/contractors-directory?search=184_g')
            ->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_pending_contractors_are_hidden(): void
    {
        $this->contractor('شركة قيد المراجعة', '903_g', 'pending');

        $this->getJson('/api/v1/contractors-directory?search=المراجعة')
            ->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_filter_by_field_and_status(): void
    {
        $this->contractor('شركة الطرق', '911_g', 'active', ['specialties' => [['field_lk_type' => 5, 'specialization_lk_type' => 1, 'classification' => 'A']]]);
        $this->contractor('شركة المباني', '912_g', 'active', ['specialties' => [['field_lk_type' => 6, 'specialization_lk_type' => 1, 'classification' => 'A']]]);
        $this->contractor('شركة موقوفة', '913_g', 'suspended', ['specialties' => [['field_lk_type' => 5, 'specialization_lk_type' => 1, 'classification' => 'A']]]);

        $this->getJson('/api/v1/contractors-directory?field_id=5')
            ->assertOk()->assertJsonCount(2, 'items');
        $this->getJson('/api/v1/contractors-directory?field_id=5&status=suspended')
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.membership_number', '913_g');
        $this->getJson('/api/v1/contractors-directory?status=expired')
            ->assertOk()->assertJsonCount(0, 'items');
    }
}
