<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Models\Membership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * dues:generate-annual — رسوم السنة الجديدة بتنزل لحالها كل 1/1 على المقاولين الفعّالين.
 */
class GenerateAnnualDuesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function contractor(string $number, string $status = 'active'): Contractor
    {
        return Contractor::create([
            'name'              => "شركة {$number}",
            'membership_number' => $number,
            'status'            => $status,
            'is_frozen'         => false,
            'classification'    => 'ثانية',
            'specialties'       => [
                ['field_lk_type' => 20, 'specialization_lk_type' => 20, 'classification' => 'ثانية'],
            ],
        ]);
    }

    private function dues(Contractor $c, int $year)
    {
        return ContractorDue::where('contractor_id', $c->id)->where('year', $year)->where('source', 'fee_engine');
    }

    public function test_generates_new_year_fees_for_active_contractors_only(): void
    {
        Carbon::setTestNow('2027-01-01 00:30:00');

        $active    = $this->contractor('960_g');
        $pending   = $this->contractor('961_g', 'pending');
        $suspended = $this->contractor('962_g', 'suspended');
        $prepaid   = $this->contractor('963_g'); // دفع 2027 مسبقاً
        Membership::create([
            'contractor_id' => $prepaid->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => '2027-01-01', 'expires_at' => '2027-12-31',
        ]);
        $lastYear = $this->contractor('964_g'); // عضويته خلصت 2026، لازم تنزل عليه 2027
        Membership::create([
            'contractor_id' => $lastYear->id, 'type' => 'renewal', 'status' => 'active',
            'starts_at' => '2026-01-01', 'expires_at' => '2026-12-31',
        ]);

        $this->artisan('dues:generate-annual')->assertSuccessful();

        $this->assertSame(1, $this->dues($active, 2027)->count());
        $this->assertGreaterThan(0, (float) $this->dues($active, 2027)->value('amount_jod'));
        $this->assertSame(1, $this->dues($lastYear, 2027)->count());
        $this->assertSame(0, $this->dues($pending, 2027)->count());
        $this->assertSame(0, $this->dues($suspended, 2027)->count());
        $this->assertSame(0, $this->dues($prepaid, 2027)->count());

        // إعادة التشغيل ما بتكرّر الذمة
        $this->artisan('dues:generate-annual')->assertSuccessful();
        $this->assertSame(1, $this->dues($active, 2027)->count());
    }

    public function test_runs_on_january_first_gaza_time(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'dues:generate-annual'));

        $this->assertNotNull($event);
        $this->assertSame('30 0 1 1 *', $event->expression);
        $this->assertSame('Asia/Gaza', $event->timezone);
    }
}
