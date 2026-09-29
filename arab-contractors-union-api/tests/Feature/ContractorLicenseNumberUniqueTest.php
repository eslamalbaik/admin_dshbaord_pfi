<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * رقم رخصة البلدية المكرر من تعديل الملف الشخصي بالتطبيق يجب أن يرجع 422 برسالة
 * واضحة — كان يصل لقيد الـ unique في قاعدة البيانات فيرجع 500 "حدث خطأ غير متوقع".
 */
class ContractorLicenseNumberUniqueTest extends TestCase
{
    use RefreshDatabase;

    private function createActive(array $attrs = []): Contractor
    {
        return Contractor::create(array_merge([
            'name'              => 'شركة اختبار',
            'password'          => Hash::make('Test@1234'),
            'status'            => 'active',
            'is_frozen'         => false,
            'phone_verified_at' => now(),
        ], $attrs));
    }

    private function actingAsContractor(Contractor $contractor): void
    {
        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);
    }

    public function test_duplicate_license_number_returns_clear_422(): void
    {
        $this->createActive(['membership_number' => '950_g', 'phone' => '0590001122', 'license_number' => '12345']);
        $me = $this->createActive(['membership_number' => '951_g', 'phone' => '0590001133']);
        $this->actingAsContractor($me);

        $response = $this->postJson('/api/v1/contractor/auth/profile/update', ['license_number' => '12345']);

        $response->assertStatus(422);
        $this->assertStringContainsString('رقم رخصة البلدية', $response->json('message'));
        $this->assertNull($me->fresh()->license_number);
    }

    public function test_contractor_can_keep_own_license_number(): void
    {
        $me = $this->createActive(['membership_number' => '951_g', 'phone' => '0590001133', 'license_number' => '12345']);
        $this->actingAsContractor($me);

        $this->postJson('/api/v1/contractor/auth/profile/update', ['license_number' => '12345'])
            ->assertOk();
    }
}
