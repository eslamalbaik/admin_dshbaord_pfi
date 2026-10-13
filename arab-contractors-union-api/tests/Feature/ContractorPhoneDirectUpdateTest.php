<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * رقم الجوال يتعدّل مباشرة من تعديل الملف التعريفي بدون OTP (قرار المالك 2026-10-13).
 */
class ContractorPhoneDirectUpdateTest extends TestCase
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

    public function test_full_profile_update_changes_phone_without_otp(): void
    {
        $contractor = $this->createActive();
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['phone' => '0599998877'])
            ->assertOk();

        $contractor->refresh();
        $this->assertSame('0599998877', $contractor->phone);
        $this->assertNotNull($contractor->phone_verified_at, 'تغيير الجوال لا يجب أن يقفل الحساب');
    }

    public function test_patch_profile_changes_phone_without_otp(): void
    {
        $contractor = $this->createActive();
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);

        $this->patchJson('/api/v1/contractor/auth/profile', ['phone' => '0597776655'])
            ->assertOk();

        $this->assertSame('0597776655', $contractor->fresh()->phone);
    }

    public function test_phone_used_by_another_contractor_is_rejected(): void
    {
        $this->createActive(['membership_number' => '951_g', 'phone' => '0591112233']);
        $contractor = $this->createActive();
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['phone' => '0591112233'])
            ->assertStatus(422);

        $this->assertSame('0590001122', $contractor->fresh()->phone);
    }

    public function test_empty_phone_keeps_current_number(): void
    {
        $contractor = $this->createActive();
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['phone' => '', 'address' => 'غزة'])
            ->assertOk();

        $contractor->refresh();
        $this->assertSame('0590001122', $contractor->phone);
        $this->assertSame('غزة', $contractor->address);
    }

    public function test_new_phone_can_log_in(): void
    {
        $contractor = $this->createActive();
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
        $this->postJson('/api/v1/contractor/auth/profile/update', ['phone' => '0599998877'])->assertOk();

        $this->assertSame($contractor->id, (new Contractor)->findForPassport('0599998877')?->id);
    }
}
