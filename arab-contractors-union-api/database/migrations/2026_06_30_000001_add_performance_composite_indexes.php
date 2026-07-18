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
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_status_created');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->index(['status', 'expires_at'], 'idx_status_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_status_created');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropIndex('idx_status_expires');
        });
    }
};
