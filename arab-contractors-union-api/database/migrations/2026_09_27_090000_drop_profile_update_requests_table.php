<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * إزالة طابور طلبات تعديل البروفايل (TASK-17 US11) — استُبدل بكتابة مباشرة على
 * contractors للحقول غير المقفلة + إشعار إدارة (ContractorProfileUpdatedNotification).
 * الحقول الحسّاسة (الهوية القانونية والمالية) بقيت مقفلة تماماً ولا تُعدَّل إلا من لوحة
 * الأدمن — لم تعد تحتاج مسار مراجعة منفصل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('profile_update_requests');
    }

    public function down(): void
    {
        // لا تراجُع: الجدول وبنيته المفصّلة موثّقان في migrations 2026_08_09_100001 و
        // 2026_09_24_110000 إن احتاج أحد استرجاعهما يدوياً.
    }
};
