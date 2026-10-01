<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('news')->where('category', 'event')->delete();
    }

    public function down(): void
    {
        // لا استرجاع هون بمعزل — migration 2 (2026_09_02_100002) بترجّع الصفوف لو رجّعت كامل السلسلة للخلف
    }
};
