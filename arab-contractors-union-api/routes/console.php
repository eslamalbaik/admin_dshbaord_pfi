<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// جلب أسعار صرف ILS/USD مقابل الدينار يومياً
Schedule::command('rates:fetch')
    ->dailyAt('06:00')
    ->onFailure(fn () => Log::channel('finance')->error('schedule: rates:fetch failed'));

// تذكيرات تجديد العضوية (بريد + SMS + إشعار) على محطات 30/14/7/3/1 يوم
Schedule::command('memberships:send-renewal-reminders')
    ->dailyAt('08:00')
    ->onFailure(fn () => Log::channel('reminders')->error('schedule: renewal reminders failed'));
