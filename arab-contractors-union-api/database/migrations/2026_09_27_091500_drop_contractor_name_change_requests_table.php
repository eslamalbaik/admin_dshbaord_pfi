<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * إزالة ميزة "طلبات تعديل اسم الشركة" بالكامل — اسم الشركة (name) حقل مقفل تمامًا،
 * لا يعدَّله المقاول من التطبيق إطلاقًا، وتعديله صلاحية لوحة الأدمن المباشرة فقط
 * (ContractorController)، بلا مسار موافقة منفصل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contractor_name_change_requests');
    }

    public function down(): void
    {
        // لا تراجُع: بنية الجدول موثّقة بـ migration 2026_07_20_000001 إن احتاج أحد استرجاعها يدوياً.
    }
};
