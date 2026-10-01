<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_dues', function (Blueprint $table) {
            $table->string('reference_number', 30)->nullable()->unique()->after('period');
        });

        // تعبئة رقم مرجعي للذمم الموجودة مسبقاً (استيراد قديم/يدوي) — INV-<سنة الإنشاء>-<id بـ3 خانات>
        DB::table('contractor_dues')->whereNull('reference_number')->orderBy('id')
            ->chunkById(200, function ($chunk) {
                foreach ($chunk as $due) {
                    $year = $due->created_at ? date('Y', strtotime($due->created_at)) : date('Y');
                    DB::table('contractor_dues')->where('id', $due->id)->update([
                        'reference_number' => 'INV-' . $year . '-' . str_pad((string) $due->id, 3, '0', STR_PAD_LEFT),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('contractor_dues', function (Blueprint $table) {
            $table->dropColumn('reference_number');
        });
    }
};
