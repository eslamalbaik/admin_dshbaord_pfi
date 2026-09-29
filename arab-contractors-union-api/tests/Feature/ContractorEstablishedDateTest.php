<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * تاريخ التأسيس (established_date) يحلّ محل سنة التأسيس (established_year): كان التطبيق
 * يستلم السنة فقط، فيعرض التاريخ فارغاً ثم يُرسله فارغاً عند الحفظ فيمسح ما أدخلته الإدارة.
 */
class ContractorEstablishedDateTest extends TestCase
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
            'name'                => 'شركة اختبار',
            'membership_number'   => '951_g',
            'commercial_register' => '563951123',
            'phone'               => '0590001133',
            'password'            => Hash::make('Test@1234'),
            'status'              => 'active',
            'is_frozen'           => false,
            'phone_verified_at'   => now(),
        ], $attrs));
    }

    private function actingAsContractor(Contractor $contractor): void
    {
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
    }

    public function test_admin_update_persists_established_date(): void
    {
        $contractor = $this->createActive();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);

        $this->postJson("/api/v1/contractors/{$contractor->id}", [
            '_method'             => 'PUT',
            'name'                => $contractor->name,
            'membership_number'   => $contractor->membership_number,
            'commercial_register' => $contractor->commercial_register,
            'established_date'    => '2015-03-04',
        ])->assertStatus(200);

        $this->assertSame('2015-03-04', $contractor->fresh()->established_date->toDateString());
    }

    public function test_profile_returns_established_date_and_not_established_year(): void
    {
        $contractor = $this->createActive(['established_date' => '2015-03-04']);
        $this->actingAsContractor($contractor);

        $resp = $this->getJson('/api/v1/contractor/auth/profile')->assertStatus(200);

        $this->assertSame('2015-03-04', $resp->json('items.established_date'));
        $this->assertArrayNotHasKey('established_year', $resp->json('items'));
    }

    public function test_app_update_with_empty_established_date_keeps_existing_value(): void
    {
        $contractor = $this->createActive(['established_date' => '2015-03-04']);
        $this->actingAsContractor($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', [
            'established_date' => '',
            'district'         => 'حي الرمال',
        ])->assertStatus(200);

        $this->assertSame('2015-03-04', $contractor->fresh()->established_date->toDateString());
    }

    public function test_app_update_can_change_established_date(): void
    {
        $contractor = $this->createActive(['established_date' => '2015-03-04']);
        $this->actingAsContractor($contractor);

        $resp = $this->postJson('/api/v1/contractor/auth/profile/update', [
            'established_date' => '2016-07-01',
        ])->assertStatus(200);

        $this->assertSame('2016-07-01', $resp->json('items.established_date'));
    }
}
