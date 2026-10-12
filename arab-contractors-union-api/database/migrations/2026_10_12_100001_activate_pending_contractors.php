<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حالة "معلّق" للمقاول كانت بس معناها "لسا ما فتح حساب بالتطبيق" (افتراض عمود status)،
 * وهاد ظاهر أصلاً بعمود "حالة الحساب". المقاول لازم يكون نشط أو منتهي، فبنحوّل المعلّقين
 * لنشط وحالتهم المعروضة بتمشي حسب الرصيد (فعّالة/منتهية) — قرار eslam 2026-10-12.
 * طلبات العضوية المعلّقة (جدول memberships) ما بتتأثر.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('contractors')->where('status', 'pending')->update(['status' => 'active']);
    }

    public function down(): void
    {
        // ما في طريقة نعرف مين كان معلّق، فالتحويل ما بيرجع
    }
};
