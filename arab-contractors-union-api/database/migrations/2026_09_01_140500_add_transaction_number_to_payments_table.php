<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_number', 30)->nullable()->unique()->after('id');
        });

        // تعبئة رقم عملية للدفعات الموجودة مسبقاً — TRX-<id بـ6 خانات>
        DB::table('payments')->whereNull('transaction_number')->orderBy('id')
            ->chunkById(200, function ($chunk) {
                foreach ($chunk as $payment) {
                    DB::table('payments')->where('id', $payment->id)->update([
                        'transaction_number' => 'TRX-' . str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('transaction_number');
        });
    }
};
