<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->integer('field_lk_type')->nullable()->after('trade');
            $table->integer('specialization_lk_type')->nullable()->after('field_lk_type');
            $table->date('established_date')->nullable()->after('established_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn(['field_lk_type', 'specialization_lk_type', 'established_date']);
        });
    }
};
