<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** إيصال قبض PDF يُولَّد ويُخزَّن مرة واحدة عند تأكيد الدفعة (REQ-19). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_pdf_path')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('receipt_pdf_path');
        });
    }
};
