<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * توحيد كل أرقام العضوية على صيغة {n}_g — الأرقام القديمة (1-927) كانت رقماً فقط.
 * آمن للتكرار: لا يلمس إلا الأرقام المكتوبة أرقاماً فقط (نُفِّذ يدوياً على الإنتاج
 * بتاريخ 2026-09-29 قبل نشر الكود، فلن يجد هناك ما يعدّله).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('contractors')
            ->whereNotNull('membership_number')
            ->orderBy('id')
            ->get(['id', 'membership_number'])
            ->filter(fn ($c) => ctype_digit((string) $c->membership_number))
            ->each(fn ($c) => DB::table('contractors')
                ->where('id', $c->id)
                ->update(['membership_number' => $c->membership_number . '_g']));
    }

    public function down(): void
    {
        DB::table('contractors')
            ->where('membership_number', 'like', '%\_g')
            ->get(['id', 'membership_number'])
            ->filter(fn ($c) => preg_match('/^([0-9]+)_g$/', $c->membership_number, $m) && (int) $m[1] <= 927)
            ->each(fn ($c) => DB::table('contractors')
                ->where('id', $c->id)
                ->update(['membership_number' => substr($c->membership_number, 0, -2)]));
    }
};
