<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('deadline');
        });

        // تاريخ النشر تلقائي — للعطاءات الموجودة أصلاً، تاريخ الإنشاء هو أقرب قيمة حقيقية لتاريخ النشر
        DB::statement('UPDATE tenders SET published_at = created_at WHERE published_at IS NULL');

        // bids_count كان عداد وهمي (بيانات seeder فقط، ولا شي بالتطبيق كان يزيده فعلياً) —
        // العطاءات عرض للمقاولين وليست نظام تقديم عروض داخل المنصة
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('bids_count');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->unsignedInteger('bids_count')->default(0)->after('status');
            $table->dropColumn('published_at');
        });
    }
};
