<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair','needs_maintenance') DEFAULT 'good'");
            DB::table('equipment')->where('condition', 'fair')->update(['condition' => 'needs_maintenance']);
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','needs_maintenance') DEFAULT 'good'");

            return;
        }

        // SQLite (بيئة الاختبارات): الـ enum بينزل كـ CHECK constraint، وما في ALTER لتعديله —
        // لازم إعادة بناء العمود. نخلّيه varchar عادي ونترك التحقق للـ validation بالكنترولر
        // (in:excellent,good,needs_maintenance). بدون هالخطوة كل insert بـ 'needs_maintenance'
        // بيرجع QueryException وبيصير التغيير غير قابل للاختبار أصلاً.
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('condition', 32)->default('good')->change();
        });

        DB::table('equipment')->where('condition', 'fair')->update(['condition' => 'needs_maintenance']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair','needs_maintenance') DEFAULT 'good'");
            DB::table('equipment')->where('condition', 'needs_maintenance')->update(['condition' => 'fair']);
            DB::statement("ALTER TABLE equipment MODIFY `condition` ENUM('excellent','good','fair') DEFAULT 'good'");

            return;
        }

        DB::table('equipment')->where('condition', 'needs_maintenance')->update(['condition' => 'fair']);

        Schema::table('equipment', function (Blueprint $table) {
            $table->enum('condition', ['excellent', 'good', 'fair'])->default('good')->change();
        });
    }
};
