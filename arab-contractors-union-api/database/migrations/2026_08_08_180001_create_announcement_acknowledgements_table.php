<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-20: تتبّع من أقرّ قراءة التعميم (لعرض Pop-up أول فتح فقط لمن لم يُقرّ بعد). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at');

            $table->unique(['contractor_id', 'announcement_id'], 'announcement_ack_contractor_announcement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_acknowledgements');
    }
};
