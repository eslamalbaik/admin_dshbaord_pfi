<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // رابط خارجي اختياري: تفاصيل العطاء أو رابط التقديم عليه
            $table->string('external_url', 500)->nullable()->after('submission_file');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('external_url');
        });
    }
};
