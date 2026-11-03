<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * العنوان (المحافظة/المدينة/الحي/الشارع/العمارة/الطابق) ورخصة البلدية وتاريخ التأسيس
 * بيعبّيهم المقاول من التطبيق — لوحة الاتحاد بتقدر تضيف المقاول بدونهم، وملفه بيضل ناقص.
 */
class ContractorAdminOptionalAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    public function test_dashboard_can_create_contractor_without_address_license_or_established_date(): void
    {
        $this->postJson('/api/v1/contractors', [
            'name'                => 'شركة بدون عنوان',
            'membership_number'   => '971_g',
            'commercial_register' => '563971123',
        ])->assertStatus(201);

        $contractor = Contractor::where('membership_number', '971_g')->firstOrFail();
        $this->assertNull($contractor->governorate_id);
        $this->assertFalse($contractor->profile_data_complete);
        $this->assertContains('العنوان التفصيلي', $contractor->missing_profile_fields);
        $this->assertContains('رقم رخصة البلدية', $contractor->missing_profile_fields);
        $this->assertContains('تاريخ التأسيس', $contractor->missing_profile_fields);
        $this->assertContains('المحافظة / المدينة', $contractor->missing_profile_fields);
    }
}
