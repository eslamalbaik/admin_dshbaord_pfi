<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حذف لاحقة "(بعد خصم ...)" من بيان الذمم المخزّن (كانت تنضاف عند الخصم وبالاستيراد القديم).
     * الخصم ضايل بحقول discount_* والمبلغ ما بيتغيّر. البيان القديم بينحفظ بجدول جانبي عشان down().
     */
    public function up(): void
    {
        Schema::create('contractor_due_description_backups', function (Blueprint $table) {
            $table->unsignedBigInteger('contractor_due_id')->primary();
            $table->text('old_description');
        });

        DB::table('contractor_dues')
            ->where('description', 'like', '%(بعد خصم%')
            ->select('id', 'description')
            ->orderBy('id')
            ->chunkById(500, function ($dues) {
                foreach ($dues as $due) {
                    $clean = trim(preg_replace('/\s*\(بعد خصم[^)]*\)/u', '', (string) $due->description));
                    if ($clean === $due->description) {
                        continue;
                    }
                    DB::table('contractor_due_description_backups')->insert([
                        'contractor_due_id' => $due->id,
                        'old_description'   => $due->description,
                    ]);
                    DB::table('contractor_dues')->where('id', $due->id)->update(['description' => $clean]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractor_due_description_backups')) {
            return;
        }

        foreach (DB::table('contractor_due_description_backups')->get() as $row) {
            DB::table('contractor_dues')->where('id', $row->contractor_due_id)
                ->update(['description' => $row->old_description]);
        }

        Schema::drop('contractor_due_description_backups');
    }
};
