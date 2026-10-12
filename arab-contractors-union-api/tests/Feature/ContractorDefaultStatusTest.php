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

    public function test_migration_turns_existing_pending_contractors_active(): void
    {
        $pending = Contractor::create([
            'name' => 'شركة معلّقة', 'membership_number' => '963_g', 'commercial_register' => '563963123', 'status' => 'pending',
        ]);
        $suspended = Contractor::create([
            'name' => 'شركة موقوفة', 'membership_number' => '964_g', 'commercial_register' => '563964123', 'status' => 'suspended',
        ]);

        (require database_path('migrations/2026_10_12_100001_activate_pending_contractors.php'))->up();

        $this->assertSame('active', $pending->fresh()->status);
        $this->assertSame('suspended', $suspended->fresh()->status);
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
