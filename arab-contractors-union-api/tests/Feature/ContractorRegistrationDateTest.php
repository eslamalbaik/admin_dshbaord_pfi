<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * تاريخ التسجيل عند إضافة مقاول: أمس أو أقدم فقط، و"اليوم" محسوب بتوقيت غزة لا UTC.
 */
class ContractorRegistrationDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']), ['*']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function create(string $date)
    {
        return $this->post('/api/v1/contractors', [
            'name'                => 'شركة اختبار للمقاولات',
            'membership_number'   => '971_g',
            'commercial_register' => '563971123',
            'governorate_id'      => 1,
            'city_id'             => 1,
            'district'            => 'الرمال',
            'building'            => 'عمارة 3',
            'floor'               => 'الثاني',
            'registration_date'   => $date,
        ], ['Accept' => 'application/json']);
    }

    public function test_yesterday_and_older_dates_are_accepted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-27 12:00', 'Asia/Gaza'));

        $this->create('2026-10-26')->assertStatus(201);
        $this->assertSame('2026-10-26', substr((string) Contractor::first()->getRawOriginal('registration_date'), 0, 10));

        Contractor::query()->forceDelete();
        $this->create('2026-10-23')->assertStatus(201);
    }

    public function test_today_and_future_dates_are_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-27 12:00', 'Asia/Gaza'));

        $this->create('2026-10-27')->assertStatus(422)->assertJsonValidationErrors('registration_date');
        $this->create('2026-10-28')->assertStatus(422)->assertJsonValidationErrors('registration_date');
        $this->assertSame(0, Contractor::count());
    }

    public function test_today_uses_gaza_time_not_utc(): void
    {
        // 01:00 بتوقيت غزة يوم 27 = ما زال يوم 26 بتوقيت UTC
        Carbon::setTestNow(Carbon::parse('2026-10-27 01:00', 'Asia/Gaza'));

        $this->create('2026-10-26')->assertStatus(201);
        Contractor::query()->forceDelete();
        // 27 هو اليوم في غزة فيُرفض، مع أن UTC كان سيعتبره الغد ويقبل 26 كـ"اليوم"
        $this->create('2026-10-27')->assertStatus(422)->assertJsonValidationErrors('registration_date');
    }
}
