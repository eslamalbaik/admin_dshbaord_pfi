<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * جملة "(بعد خصم ...)" ما عادت تنحفظ ببيان الذمة (الخصم إله حقول discount_*)،
 * والـmigration بيشيلها من الذمم القديمة وبيرجّعها بالـdown.
 */
class DueDiscountSuffixCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_strips_stored_discount_suffix_and_can_restore_it(): void
    {
        $contractor = Contractor::create([
            'name'                => 'شركة 930_g للمقاولات',
            'membership_number'   => '930_g',
            'commercial_register' => '512345678',
            'status'              => 'active',
            'is_frozen'           => false,
        ]);

        $make = fn (string $description) => ContractorDue::create([
            'contractor_id' => $contractor->id,
            'year'          => 2026,
            'description'   => $description,
            'amount_jod'    => 72,
            'paid_jod'      => 0,
            'status'        => 'unpaid',
            'source'        => 'manual',
        ]);

        $fixed   = $make('ذمة مالية 100 دينار (بعد خصم 28 د.أ)');
        $percent = $make('رسوم اشتراك سنة 2026 (محرّك الاحتساب الآلي) (بعد خصم 10%)');
        $plain   = $make('رسوم عضوية 2026');

        $migration = require database_path('migrations/2026_10_10_000002_strip_discount_suffix_from_due_descriptions.php');
        $migration->down();
        $migration->up();

        $this->assertSame('ذمة مالية 100 دينار', $fixed->fresh()->description);
        $this->assertSame('رسوم اشتراك سنة 2026 (محرّك الاحتساب الآلي)', $percent->fresh()->description);
        $this->assertSame('رسوم عضوية 2026', $plain->fresh()->description);
        $this->assertSame(2, DB::table('contractor_due_description_backups')->count());

        $migration->down();

        $this->assertSame('ذمة مالية 100 دينار (بعد خصم 28 د.أ)', $fixed->fresh()->description);
        $this->assertSame('رسوم اشتراك سنة 2026 (محرّك الاحتساب الآلي) (بعد خصم 10%)', $percent->fresh()->description);

        $migration->up();
    }
}
