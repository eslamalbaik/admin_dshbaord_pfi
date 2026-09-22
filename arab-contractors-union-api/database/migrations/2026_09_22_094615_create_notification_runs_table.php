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
        Schema::create('notification_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('job_name', ['grace_period', 'renewal', 'announcement'])->index();
            $table->date('run_date')->index();
            $table->enum('status', ['started', 'completed', 'failed'])->default('started');
            $table->integer('contractor_count')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Unique index to prevent duplicate runs for the same job on the same date
            $table->unique(['job_name', 'run_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_runs');
    }
};
