<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * كانت تجبر كل ذمة لدينار كامل لفوق، وانلغى هالجبر (قاعدة eslam 14/10، انظر
     * 2026_10_14_000001). صارت بتجهّز جدول النسخ الاحتياطية بس، عشان اللي نفّذها على staging
     * وعشان مهاجرة الاسترجاع تلاقي جدولها بكل البيئات.
     */
    public function up(): void
    {
        if (! Schema::hasTable('contractor_due_amount_backups')) {
            Schema::create('contractor_due_amount_backups', function (Blueprint $table) {
                $table->unsignedBigInteger('contractor_due_id')->primary();
                $table->decimal('old_amount_jod', 10, 2);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_due_amount_backups');
    }
};
