<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ نشر الآلية (مباشر / مجدول) — الآلية المجدولة ما بتظهر بالسوق قبل موعدها.
 * null = منشورة (كل الآليات الموجودة قبل هالحقل تضل ظاهرة متل ما هي).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
            $table->dropColumn('published_at');
        });
    }
};
