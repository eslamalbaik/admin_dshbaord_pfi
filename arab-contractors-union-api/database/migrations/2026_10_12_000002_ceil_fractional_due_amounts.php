<?php

use App\Models\Contractor;
use App\Models\ContractorDue;
use App\Services\BalanceRoundingService;
use App\Services\ContractorCreditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جبر كسور كل ذمة موجودة لدينار كامل لفوق (45.20 ← 46). جبر الرصيد اللي كان مضاف على
     * ذمة (rounding_jod) بيرجع منها أولاً لأنه هلأ جزء من كسر الذمة نفسها. الذمة المسدَّدة
     * بيضل عليها الفرق (0.80) وبينسدّ من رصيد الشركة إن وجد. المبلغ القديم بينحفظ بجدول جانبي.
     */
    public function up(): void
    {
        Schema::create('contractor_due_amount_backups', function ($table) {
            $table->unsignedBigInteger('contractor_due_id')->primary();
            $table->decimal('old_amount_jod', 10, 2);
        });

        $contractors = [];
        ContractorDue::query()
            ->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', '!=', BalanceRoundingService::TAG))
            ->where(fn ($q) => $q->whereRaw('ROUND(amount_jod * 100, 0) % 100 <> 0')->orWhere('rounding_jod', '>', 0))
            ->orderBy('id')
            ->each(function (ContractorDue $due) use (&$contractors) {
                $base   = round((float) $due->amount_jod - (float) $due->rounding_jod, 2);
                $amount = ContractorDue::ceilToDinar($base);

                DB::table('contractor_due_amount_backups')->insert([
                    'contractor_due_id' => $due->id, 'old_amount_jod' => $due->amount_jod,
                ]);

                $paid = round((float) $due->paid_jod, 2);
                $due->forceFill([
                    'amount_jod'   => $amount,
                    'rounding_jod' => 0,
                    'status'       => $paid >= $amount && $paid > 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'unpaid'),
                ])->save();

                $contractors[$due->contractor_id] = true;
            });

        foreach (array_keys($contractors) as $id) {
            try {
                if ($contractor = Contractor::find($id)) {
                    app(ContractorCreditService::class)->applyAvailableCredit($contractor);
                    app(BalanceRoundingService::class)->sync($id);
                }
            } catch (\Throwable $e) {
                // balances:round الليلي بيكمّل جبر الرصيد
                report($e);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('contractor_due_amount_backups')->get() as $row) {
            DB::table('contractor_dues')->where('id', $row->contractor_due_id)
                ->update(['amount_jod' => $row->old_amount_jod]);
        }

        Schema::dropIfExists('contractor_due_amount_backups');
    }
};
