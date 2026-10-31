<?php

use App\Services\BalanceRoundingService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * الكسور ما بتنجبر (قاعدة eslam 31/10): بترجّع كل رصيد دائن / فائض دفعة انجبر لتحت (181.40 ← 181)
     * لقيمته الأصلية، وبتشيل أي قيد "جبر كسور الرصيد" قديم.
     */
    public function up(): void
    {
        try {
            app(BalanceRoundingService::class)->syncAll();
        } catch (\Throwable $e) {
            // balances:round الليلي بيكمّل
            report($e);
        }
    }

    public function down(): void
    {
        // ما في رجعة: الجبر انلغى
    }
};
