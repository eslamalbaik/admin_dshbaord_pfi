<?php

use App\Models\Contractor;
use App\Services\BalanceRoundingService;
use App\Services\ContractorCreditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الذمم ما بتنجبر (قاعدة eslam 14/10): بترجّع كل ذمة انجبرت بـ2026_10_12_000002 لمبلغها
     * قبل الجبر. جدول النسخ بيضل محفوظ. الذمة اللي انسدّ منها شي بتتحدّث حالتها حسب المسدَّد.
     */
    public function up(): void
    {
        if (! Schema::hasTable('contractor_due_amount_backups')) {
            return;
        }

        $contractors = [];
        foreach (DB::table('contractor_due_amount_backups')->get() as $row) {
            $due = DB::table('contractor_dues')->where('id', $row->contractor_due_id)->first();
            if (! $due) {
                continue;
            }

            $paid   = round((float) $due->paid_jod, 2);
            $amount = round((float) $row->old_amount_jod, 2);

            DB::table('contractor_dues')->where('id', $due->id)->update([
                'amount_jod'   => $amount,
                'rounding_jod' => 0,
                'status'       => $paid >= $amount && $paid > 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
            ]);

            $contractors[$due->contractor_id] = true;
        }

        foreach (array_keys($contractors) as $id) {
            try {
                if ($contractor = Contractor::find($id)) {
                    app(BalanceRoundingService::class)->sync($id);
                    app(ContractorCreditService::class)->applyAvailableCredit($contractor);
                }
            } catch (\Throwable $e) {
                // balances:round الليلي بيكمّل
                report($e);
            }
        }
    }

    public function down(): void
    {
        // ما في رجعة: الجبر انلغى
    }
};
