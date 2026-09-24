<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// جلب أسعار صرف ILS/USD مقابل الدينار يومياً (مصدر عام احتياطي مبكر)
Schedule::command('rates:fetch')
    ->dailyAt('06:00')
    ->onFailure(fn () => Log::channel('finance')->error('schedule: rates:fetch failed'));

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
Schedule::command('rates:fetch-pma')
    ->weekdays()
    ->timezone('Asia/Gaza')
    ->at('11:00')
    ->onFailure(fn () => Log::channel('finance')->error('schedule: rates:fetch-pma failed'));

// تذكيرات تجديد العضوية (بريد + SMS + إشعار) على محطات 30/14/7/3/1 يوم
Schedule::command('memberships:send-renewal-reminders')
    ->dailyAt('08:00')
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: renewal reminders failed'));

// تذكيرات فترة السماح بعد انتهاء العضوية (بداية/منتصف/نهاية الفترة) — الوصول للتطبيق يبقى مسموحاً خلالها
Schedule::command('memberships:send-grace-period-reminders')
    ->dailyAt('08:05')
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: grace period reminders failed'));

// نسخة احتياطية يومية لقاعدة البيانات وملفات storage/app/public + تنظيف النسخ القديمة أسبوعياً
Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: backup:run failed'));

Schedule::command('backup:clean')
    ->weekly()
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: backup:clean failed'));

// أرشفة العطاءات المنتهية يومياً (REQ-09) + حذف المؤرشف منذ أكثر من 12 شهراً أسبوعياً (REQ-10)
Schedule::command('tenders:archive')
    ->dailyAt('01:00')
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: tenders:archive failed'));

Schedule::command('tenders:purge')
    ->weekly()
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: tenders:purge failed'));

// أرشفة الفعاليات المنتهية يومياً — نفس نمط tenders:archive
Schedule::command('events:archive')
    ->dailyAt('01:05')
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: events:archive failed'));
