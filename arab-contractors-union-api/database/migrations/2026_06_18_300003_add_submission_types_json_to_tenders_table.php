<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // مصفوفة JSON تحمل قيماً متعددة مثل ["email","phone"]
            $table->json('submission_types')->nullable()->after('submission_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('submission_types');
        });
    }
};
