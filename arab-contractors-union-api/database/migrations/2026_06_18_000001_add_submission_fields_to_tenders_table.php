<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->enum('submission_type', ['email', 'phone', 'file'])->nullable()->after('status');
            $table->string('submission_email')->nullable()->after('submission_type');
            $table->string('submission_phone')->nullable()->after('submission_email');
            $table->string('submission_file')->nullable()->after('submission_phone');
        });
    }

    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn(['submission_type', 'submission_email', 'submission_phone', 'submission_file']);
        });
    }
};
