<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3);                 // ILS | USD
            $table->decimal('rate_to_jod', 12, 6);         // 1 وحدة من العملة = كم دينار أردني
            $table->timestamp('fetched_at');
            $table->string('source', 20)->default('api');  // api | manual
            $table->timestamps();

            $table->index(['currency', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
