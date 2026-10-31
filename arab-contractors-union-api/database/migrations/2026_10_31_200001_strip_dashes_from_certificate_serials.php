<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الرقم التسلسلي صار حروفاً وأرقاماً بدون شرطات (الشرطات كانت تتلخبط عند نسخ الرقم من PDF عربي).
 * بنشيل الشرطات من الأرقام المخزّنة بالصيغ التجريبية السابقة؛ المرجعي القديم MC-000123 ما بنلمسه.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('certificate_requests')
            ->whereNotNull('serial_number')
            ->where('serial_number', 'not like', 'MC-%')
            ->where('serial_number', 'like', '%-%')
            ->update(['serial_number' => DB::raw("REPLACE(serial_number, '-', '')")]);
    }

    public function down(): void
    {
        // لا رجعة: الصيغة السابقة كانت تجريبية على staging فقط
    }
};
