<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * تنبيه موحَّد لفشل أي مهمة مجدولة.
 *
 * كل أمر يخرج بكود غير صفري (أو يرمي استثناءً غير ملتقط) يُسجَّل بمستوى critical — لا error —
 * حتى يبرز وسط السطور الروتينية في نفس السجل، ويُرسَل بريد بمخرجاته إن ضُبط
 * notifications.scheduling.alert_email.
 *
 * هذا نصف المعادلة فقط: الأمر نفسه يجب ألا يبتلع استثناءاته ويعيد 0، وإلا لن يُستدعى onFailure
 * إطلاقاً — وهو ما أخفى عطلَي أعمدة SQL في أوامر التذكير شهوراً (راجع e21a8a9).
 */
$alertOnFailure = function (Event $event, string $task, string $channel = 'reminders'): Event {
    $event->onFailure(fn () => Log::channel($channel)->critical("schedule: {$task} failed", [
        'task' => $task,
        'hint' => 'scheduled task exited non-zero — check the matching *.failed entry for the trace',
    ]));

    if ($alertEmail = config('notifications.scheduling.alert_email')) {
        $event->emailOutputOnFailure($alertEmail);
    }

    return $event;
};

// جلب أسعار صرف ILS/USD مقابل الدينار يومياً (مصدر عام احتياطي مبكر)
$alertOnFailure(
    Schedule::command('rates:fetch')->dailyAt('06:00'),
    'rates:fetch',
    'finance'
);

// جلب أسعار سلطة النقد الرسمية (PMA) — أيام العمل فقط، بعد صدور النشرة الرسمية.
// تُشغَّل بعد rates:fetch عمداً: لو نجحت تصبح "الأحدث" فتُستخدم بدل السعر العام (REQ-17).
// السبت/الأحد مستبعدان أصلاً بـweekdays()، والجمعة يُستبعد داخلياً بالأمر نفسه
// (تأكيد ميداني: كلا المصدرين لا يُحدَّثان الجمعة والسبت) — تُعتمد آخر أسعار محفوظة تلقائياً.
//
// الساعة 11:00 وليست 07:45: القياس الفعلي يوم 2026-09-24 أظهر أن المصدرين لا يكونان
// قد نشرا سعر اليوم بعد عند 07:45 — supabase كان يعيد تاريخ الأمس (04:45 UTC) ولم
// يتحدّث إلا لاحقاً (07:25 UTC)، وPMA بالمثل. فكانت المهمة تفشل بـstale_date كل صباح
// وتُطلق إشعار "تعذّر جلب سعر الصرف" للأدمن يومياً بلا سبب حقيقي.
// 11:00 بتوقيت غزة تترك هامشاً مريحاً بعد النشر. التطبيق لا يتأثر بالتأخير لأن
// rates:fetch الساعة 06:00 يضمن وجود سعر صالح دائماً؛ المتأخر هو تسجيل السعر الرسمي فقط.
$alertOnFailure(
    Schedule::command('rates:fetch-pma')->weekdays()->timezone('Asia/Gaza')->at('11:00'),
    'rates:fetch-pma',
    'finance'
);

// تذكيرات تجديد العضوية (بريد + SMS + إشعار) على محطات 30/14/7/3/1 يوم
$alertOnFailure(
    Schedule::command('memberships:send-renewal-reminders')->dailyAt('08:00'),
    'renewal reminders'
);

// تذكيرات فترة السماح بعد انتهاء العضوية (بداية/منتصف/نهاية الفترة) — الوصول للتطبيق يبقى مسموحاً خلالها
$alertOnFailure(
    Schedule::command('memberships:send-grace-period-reminders')->dailyAt('08:05'),
    'grace period reminders'
);

// نسخة احتياطية يومية لقاعدة البيانات وملفات storage/app/public + تنظيف النسخ القديمة أسبوعياً
$alertOnFailure(
    Schedule::command('backup:run')->dailyAt('02:00'),
    'backup:run'
);

$alertOnFailure(
    Schedule::command('backup:clean')->weekly(),
    'backup:clean'
);

// أرشفة العطاءات المنتهية يومياً (REQ-09) + حذف المؤرشف منذ أكثر من 12 شهراً أسبوعياً (REQ-10)
$alertOnFailure(
    Schedule::command('tenders:archive')->dailyAt('01:00'),
    'tenders:archive'
);

$alertOnFailure(
    Schedule::command('tenders:purge')->weekly(),
    'tenders:purge'
);

// أرشفة الفعاليات المنتهية يومياً — نفس نمط tenders:archive
$alertOnFailure(
    Schedule::command('events:archive')->dailyAt('01:05'),
    'events:archive'
);

// تنظيف مستندات طلبات تعديل الملف المرحَّلة التي لم تبقَ معلّقة (TASK-17 US11).
// أسبوعي لا يومي: المسار الطبيعي (موافقة/رفض/إلغاء) ينظّف نفسه، وهذا للحالات الشاذّة وحدها.
$alertOnFailure(
    Schedule::command('profile-requests:prune-staged')->weekly(),
    'profile-requests:prune-staged'
);
