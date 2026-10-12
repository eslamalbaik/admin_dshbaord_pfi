<?php

use App\Services\BalanceRoundingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جبر كسور الرصيد بينضاف على قيمة ذمة/رصيد/دفعة موجودة بدل قيد منفصل "جبر كسور الرصيد".
     * rounding_jod = كم من قيمة هالسطر هو جبر (موجب دايماً):
     * - ذمة: مضاف على amount_jod (991.40 ← 992 وrounding_jod = 0.60).
     * - رصيد دائن: منقوص من amount_jod (181.40 ← 181 وrounding_jod = 0.40).
     * - دفعة: مبلغها ما بيتغيّر؛ الكسر محسوب ضمن used_amount_jod فما بيضل رصيد.
     *
     * بعدها بنحوّل قيود الجبر المنفصلة المفتوحة للشكل الجديد مباشرة.
     */
    public function up(): void
    {
        foreach (['contractor_dues', 'contractor_credits', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('rounding_jod', 10, 2)->default(0);
            });
        }

        try {
            app(BalanceRoundingService::class)->syncAll();
        } catch (\Throwable $e) {
            // balances:round الليلي بيكمّل التحويل
            report($e);
        }
    }

    public function down(): void
    {
        foreach (['contractor_dues', 'contractor_credits', 'payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('rounding_jod');
            });
        }
    }
};
