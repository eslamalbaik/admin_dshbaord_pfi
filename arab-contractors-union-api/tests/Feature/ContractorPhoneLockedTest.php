<?php

namespace Tests\Feature;

use App\Models\Contractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * رقم الجوال لا يُغيَّر من التطبيق — من لوحة الإدارة فقط (قرار المالك 2026-11-05، يلغي
 * التعديل المباشر المعتمد 2026-10-13).
 */
class ContractorPhoneLockedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsContractor(array $attrs = []): Contractor
    {
        $contractor = Contractor::create(array_merge([
            'name'                    => 'شركة اختبار',
            'membership_number'       => '950_g',
            'phone'                   => '0590001122',
            'authorized_person_phone' => '0597000111',
            'password'                => Hash::make('Test@1234'),
            'status'                  => 'active',
            'is_frozen'               => false,
            'phone_verified_at'       => now(),
        ], $attrs));

        Sanctum::actingAs($contractor, ['*']);
        app('auth')->guard('contractor')->setUser($contractor);

        return $contractor;
    }

    public static function profileRoutes(): array
    {
        return [
            'PATCH profile'       => ['PATCH', '/api/v1/contractor/auth/profile'],
            'POST profile/update' => ['POST', '/api/v1/contractor/auth/profile/update'],
        ];
    }

    /** @dataProvider profileRoutes */
    public function test_profile_save_refuses_a_different_number(string $method, string $uri): void
    {
        $contractor = $this->actingAsContractor();

        $this->json($method, $uri, ['phone' => '0599998877', 'address' => 'غزة'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'phone_change_not_allowed');

        $contractor->refresh();
        $this->assertSame('0590001122', $contractor->phone);
        $this->assertNotSame('غزة', $contractor->address, 'الطلب المرفوض لا يحفظ شيئاً');
    }

    /** @dataProvider profileRoutes */
    public function test_another_contractors_number_is_refused(string $method, string $uri): void
    {
        $this->actingAsContractor(['membership_number' => '951_g', 'phone' => '0591112233', 'authorized_person_phone' => null]);
        $contractor = $this->actingAsContractor();

        $this->json($method, $uri, ['phone' => '0591112233'])->assertStatus(403);

        $this->assertSame('0590001122', $contractor->fresh()->phone);
    }

    /**
     * التطبيق يعيد إرسال الرقم الحالي مع كل حفظ — بأي صيغة، أو جوال المفوض، أو فارغ.
     *
     * @dataProvider profileRoutes
     */
    public function test_profile_save_accepts_the_current_number(string $method, string $uri): void
    {
        $contractor = $this->actingAsContractor();

        foreach (['+970 590 001 122', '590001122', '0597000111', ''] as $i => $phone) {
            $this->json($method, $uri, ['phone' => $phone, 'address' => "غزة {$i}"])->assertOk();
            $this->assertSame("غزة {$i}", $contractor->fresh()->address);
        }

        $this->assertSame('0590001122', $contractor->fresh()->phone);
        $this->assertNotNull($contractor->fresh()->phone_verified_at);
    }

    public function test_phone_change_otp_endpoints_are_refused(): void
    {
        $this->actingAsContractor();

        $this->postJson('/api/v1/contractor/auth/profile/phone/request-otp', ['phone' => '0599998877'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'phone_change_not_allowed');

        $this->postJson('/api/v1/contractor/auth/profile/phone/verify-otp', ['otp' => '123456'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'phone_change_not_allowed');
    }
}
