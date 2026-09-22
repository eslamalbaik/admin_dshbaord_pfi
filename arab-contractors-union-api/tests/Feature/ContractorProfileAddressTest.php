<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * يضمن أن GET /contractor/auth/profile يُعيد حقول العنوان الكاملة للتطبيق —
 * المحافظة (كأوبجكت id+name)، الحي، العمارة، الطابق — حتى لو أدخلها الأدمن
 * من لوحة التحكم وليس المقاول نفسه من التطبيق. (TASK-15 #5 — Mobile side)
 */
class ContractorProfileAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function createActive(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار',
            'membership_number' => '950_g',
            'phone'             => '0590001122',
            'password'          => Hash::make('Test@1234'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ], $attrs));
    }

    /**
     * يُفعّل المقاول على كلا الغاردين:
     *  - 'sanctum': حتى يتجاوز middleware الـ auth:sanctum
     *  - 'contractor': حتى يُرجع $request->user('contractor') في الكونترولر
     */
    private function actingAsContractor(Contractor $contractor): void
    {
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  GET /contractor/auth/profile — address fields always present
    // ─────────────────────────────────────────────────────────────────────

    public function test_profile_returns_address_fields_set_by_admin(): void
    {
        // firstOrCreate to avoid collision with the seeded "غزة" governorate
        $gov = Governorate::firstOrCreate(['name' => 'غزة']);

        $contractor = $this->createActive([
            'governorate_id' => $gov->id,
            'district'       => 'حي الرمال',
            'building'       => 'عمارة النور',
            'floor'          => 'الثالث',
        ]);

        $this->actingAsContractor($contractor);

        $resp = $this->getJson('/api/v1/contractor/auth/profile');
        $resp->assertStatus(200);

        // المحافظة ترجع كأوبجكت لا كـ id مجرد
        $resp->assertJsonPath('items.governorate.id',   $gov->id)
             ->assertJsonPath('items.governorate.name', 'غزة')
             ->assertJsonPath('items.district',  'حي الرمال')
             ->assertJsonPath('items.building',  'عمارة النور')
             ->assertJsonPath('items.floor',     'الثالث');
    }

    public function test_profile_returns_null_governorate_when_not_set(): void
    {
        $contractor = $this->createActive();
        $this->actingAsContractor($contractor);

        $resp = $this->getJson('/api/v1/contractor/auth/profile');
        $resp->assertStatus(200)
             ->assertJsonPath('items.governorate', null)
             ->assertJsonPath('items.district',    null)
             ->assertJsonPath('items.building',    null)
             ->assertJsonPath('items.floor',       null);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  POST /contractor/auth/profile/update — contractor can update district
    // ─────────────────────────────────────────────────────────────────────

    public function test_update_full_profile_persists_district(): void
    {
        $contractor = $this->createActive();
        $this->actingAsContractor($contractor);

        $resp = $this->postJson('/api/v1/contractor/auth/profile/update', [
            'district' => 'حي الزيتون',
            'building' => 'برج الفجر',
            'floor'    => 'الأول',
        ]);

        $resp->assertStatus(200)
             ->assertJsonPath('items.district', 'حي الزيتون')
             ->assertJsonPath('items.building', 'برج الفجر')
             ->assertJsonPath('items.floor',    'الأول');

        $this->assertDatabaseHas('contractors', [
            'id'       => $contractor->id,
            'district' => 'حي الزيتون',
            'building' => 'برج الفجر',
            'floor'    => 'الأول',
        ]);
    }

    public function test_update_full_profile_requires_auth(): void
    {
        $this->postJson('/api/v1/contractor/auth/profile/update', ['district' => 'x'])
             ->assertStatus(401);
    }
}
