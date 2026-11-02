<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * جوال المفوض صار يقبل أرقام دول غير فلسطين كمعرّف دخول — إعادة توحيد الأرقام المحفوظة
 * (مثلاً 9620791234567 ← 962791234567) عشان المطابقة عند الدخول تكون مباشرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('contractors')
            ->whereNotNull('authorized_person_phone')
            ->where('authorized_person_phone', '!=', '')
            ->select('id', 'authorized_person_phone')
            ->orderBy('id')
            ->each(function ($row) {
                $normalized = PhoneNumber::normalize($row->authorized_person_phone);
                if ($normalized !== $row->authorized_person_phone) {
                    DB::table('contractors')->where('id', $row->id)->update(['authorized_person_phone' => $normalized]);
                }
            });
    }

    public function down(): void
    {
        // توحيد صيغة فقط — لا شيء للتراجع عنه
    }
};
