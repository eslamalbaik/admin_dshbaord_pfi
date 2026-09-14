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
        Schema::table('events', function (Blueprint $table) {
            // نوع الفعالية — اختيار واحد فقط من ثلاثة (دولية/مؤسسات/محلية للاتحاد)، يحل محل is_international
            // الثنائي الناقص. is_international يبقى مؤقتاً alias محسوب (deprecated) للتوافق الخلفي.
            $table->enum('event_type', ['international', 'institutional', 'local'])
                ->default('local')
                ->after('is_international');
        });

        DB::table('events')->where('is_international', true)->update(['event_type' => 'international']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_type');
        });
    }
};
