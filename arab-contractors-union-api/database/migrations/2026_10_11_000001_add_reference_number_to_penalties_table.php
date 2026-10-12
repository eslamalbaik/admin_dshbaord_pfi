<?php

use App\Models\Penalty;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->after('contractor_id');
        });

        DB::table('penalties')->whereNull('reference_number')->orderBy('id')->each(function ($row) {
            DB::table('penalties')->where('id', $row->id)->update([
                'reference_number' => Penalty::referenceFor((int) $row->id, $row->created_at),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            $table->dropColumn('reference_number');
        });
    }
};
