<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** REQ-11: حفظ/تفضيل العطاءات (Bookmark) لكل مقاول. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contractor_id', 'tender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_bookmarks');
    }
};
