<?php

namespace Tests\Feature;

use App\Enums\JobName;
use App\Models\NotificationRun;
use App\Services\IdempotencyService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * يحرس ظهور أعطال المهام المجدولة.
 *
 * الخلفية: أمرا التذكير كانا يلفّان handle() كله في try/catch واحد يسجّل ويعيد 1، فظلّ
 * عطلا عمودين في SQL (contractors.membership_expires_at و contractor_dues.paid_amount/amount)
 * يرميان يومياً في الإنتاج دون أن ينتبه أحد — الكتل القديمة قبلهما كانت تنجح فيبدو الأمر سليماً.
 *
 * هذه الاختبارات تثبّت أن فشل الإعداد/الاستعلام يُعاد رميه (لا يُبتلع) وأن كل مهمة مجدولة
 * لديها خطّاف فشل مسجَّل.
 */
class ScheduledCommandFailureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * فشل استعلام داخل أمر تذكير التجديد يجب أن يخرج من handle() لا أن يُبتلع.
     */
    public function test_renewal_reminder_propagates_query_failure(): void
    {
        // يحاكي عطل العمود الأصلي: الجدول الذي يستعلمه الأمر لم يعد موجوداً
        Schema::drop('memberships');

        $thrown = $this->runAndCaptureThrowable('memberships:send-renewal-reminders');

        $this->assertInstanceOf(
            QueryException::class,
            $thrown,
            'A query failure must propagate out of handle() — swallowing it and returning 1 is what hid the column bugs'
        );

        // التشغيل يُوسم فاشلاً مع سبب العطل قبل إعادة رمي الاستثناء
        $run = NotificationRun::where('job_name', JobName::RENEWAL)->first();
        $this->assertNotNull($run, 'A notification run should have been recorded');
        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->error_message);
    }

    /**
     * نفس الضمانة لأمر فترة السماح.
     */
    public function test_grace_period_reminder_propagates_query_failure(): void
    {
        Schema::drop('memberships');

        $thrown = $this->runAndCaptureThrowable('memberships:send-grace-period-reminders');

        $this->assertInstanceOf(
            QueryException::class,
            $thrown,
            'A query failure must propagate out of handle() — swallowing it and returning 1 is what hid the column bugs'
        );

        $run = NotificationRun::where('job_name', JobName::GRACE_PERIOD)->first();
        $this->assertNotNull($run, 'A notification run should have been recorded');
        $this->assertSame('failed', $run->status);
        $this->assertNotEmpty($run->error_message);
    }

    /**
     * تشغيل فاشل/متوقف لا يمنع إعادة المحاولة في نفس اليوم — وإلا لبقي العطل ثابتاً
     * حتى نهاية اليوم ولاضطر المشغّل لحذف السجل يدوياً.
     */
    public function test_failed_run_does_not_block_a_retry_on_the_same_date(): void
    {
        $service = app(IdempotencyService::class);
        $today = today()->toDateString();

        foreach (['started', 'failed'] as $status) {
            NotificationRun::query()->delete();
            NotificationRun::create([
                'job_name' => JobName::RENEWAL,
                'run_date' => $today,
                'status' => $status,
            ]);

            $this->assertTrue(
                $service->runAlreadyExists(JobName::RENEWAL, $today),
                "A '{$status}' run still counts as existing"
            );
            $this->assertFalse(
                $service->completedRunExists(JobName::RENEWAL, $today),
                "A '{$status}' run must not be treated as completed, or retries are blocked"
            );
        }
    }

    /**
     * تشغيل مكتمل فعلاً هو وحده ما يمنع التكرار.
     */
    public function test_completed_run_blocks_a_duplicate_run(): void
    {
        $today = today()->toDateString();

        NotificationRun::create([
            'job_name' => JobName::RENEWAL,
            'run_date' => $today,
            'status' => 'completed',
            'contractor_count' => 3,
        ]);

        $this->assertTrue(app(IdempotencyService::class)->completedRunExists(JobName::RENEWAL, $today));
    }

    /**
     * كل مهمة مجدولة يجب أن تملك خطّاف فشل — بلا هذا يخرج الأمر بكود غير صفري بصمت.
     */
    public function test_every_scheduled_command_registers_a_failure_hook(): void
    {
        $this->app->make(ConsoleKernel::class)->bootstrap();

        $events = $this->app->make(Schedule::class)->events();

        $this->assertNotEmpty($events, 'No scheduled events were loaded from routes/console.php');

        foreach ($events as $event) {
            $this->assertNotEmpty(
                $this->afterCallbacksOf($event),
                "Scheduled task [{$event->getSummaryForDisplay()}] has no failure hook — "
                    . 'a non-zero exit would go unnoticed. Wire it through $alertOnFailure in routes/console.php.'
            );
        }
    }

    /**
     * يشغّل أمراً ويعيد ما رماه (أو null) — Artisan::call يعطّل التقاط الاستثناءات،
     * فما يخرج من handle() يصل إلى هنا كما هو.
     */
    private function runAndCaptureThrowable(string $command): ?\Throwable
    {
        try {
            Artisan::call($command);
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    /**
     * afterCallbacks محمية — onFailure/emailOutputOnFailure تسجّل فيها عبر then().
     */
    private function afterCallbacksOf(Event $event): array
    {
        $property = new ReflectionProperty(Event::class, 'afterCallbacks');
        $property->setAccessible(true);

        return $property->getValue($event);
    }
}
