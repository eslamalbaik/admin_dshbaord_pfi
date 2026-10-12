<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * المقاول اللي بتضيفه الإدارة كان يطلع "معلّق" (افتراض عمود status بالـDB) لحد ما يفتح
 * حساب بالتطبيق. المقاول لازم يكون نشط أو منتهي، و"معلّق" ما بينختار يدوياً.
 */
class ContractorDefaultStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    public function test_contractor_created_from_dashboard_starts_active(): void
    {
        $this->postJson('/api/v1/contractors', [
            'name'                => 'شركة اختبار للمقاولات',
            'membership_number'   => '961_g',
            'commercial_register' => '563961123',
            'governorate_id'      => 1,
            'city_id'             => 1,
            'district'            => 'الرمال',
            'building'            => 'عمارة 3',
            'floor'               => 'الثاني',
        ])->assertStatus(201);

        $this->assertSame('active', Contractor::where('membership_number', '961_g')->value('status'));
    }

    public function test_pending_cannot_be_chosen_manually(): void
    {
        $contractor = Contractor::create([
            'name'                => 'شركة',
            'membership_number'   => '962_g',
            'commercial_register' => '563962123',
            'status'              => 'active',
        ]);

        $this->patchJson("/api/v1/contractors/{$contractor->id}/status", ['status' => 'pending'])
            ->assertStatus(422);

        $this->patchJson("/api/v1/contractors/{$contractor->id}/status", ['status' => 'expired'])
            ->assertSuccessful();
        $this->assertSame('expired', $contractor->fresh()->status);
    }
}
