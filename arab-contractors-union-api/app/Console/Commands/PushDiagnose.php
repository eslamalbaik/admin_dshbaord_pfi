<?php

namespace App\Console\Commands;

use App\Models\Contractor;
use App\Services\Push\FirebasePushSender;
use App\Services\Push\LogPushSender;
use App\Services\Push\PushSenderInterface;
use Illuminate\Console\Command;

/**
 * تشخيص إعداد إشعارات Push (FCM).
 *
 * السبب في وجود هذا الأمر: الـ binding في AppServiceProvider يسقط تلقائياً إلى
 * LogPushSender حين لا يوجد FIREBASE_CREDENTIALS صالح — وLogPushSender يرجّع true
 * دائماً. يعني الإرسال "ينجح" في السجلات بينما لا يصل أي جهاز. هذا الأمر يكشف
 * أيّ مرسل مُفعَّل فعلياً بدل التخمين.
 */
class PushDiagnose extends Command
{
    protected $signature = 'push:diagnose
                            {--to= : رقم عضوية مقاول لإرسال إشعار تجريبي إليه فعلياً}';

    protected $description = 'فحص إعداد Firebase Cloud Messaging وبيان ما إذا كان الإرسال فعلياً أم وضع log فقط';

    public function handle(): int
    {
        $credentials = config('services.firebase.credentials');
        $sender      = app(PushSenderInterface::class);
        $isLive      = $sender instanceof FirebasePushSender;

        $this->line('');
        $this->line('<comment>إعداد Firebase Cloud Messaging</comment>');
        $this->table(['البند', 'القيمة'], [
            ['FIREBASE_CREDENTIALS', $credentials ?: '<fg=red>غير مُعرَّف</>'],
            ['ملف الاعتماد موجود', $credentials && file_exists($credentials) ? '<fg=green>نعم</>' : '<fg=red>لا</>'],
            ['المرسل المُفعَّل', $isLive
                ? '<fg=green>FirebasePushSender (إرسال فعلي)</>'
                : '<fg=yellow>LogPushSender (سجل فقط — لا يصل أي جهاز)</>'],
        ]);

        if (! $isLive) {
            $this->warn('وضع log: كل إشعار push سيُكتب في storage/logs (قناة push) ويرجّع "نجاح" دون إرساله.');
            $this->line('للتفعيل: نزّل ملف Service Account من Firebase Console، ضعه خارج مجلد public،');
            $this->line('ثم عرّف مساره المطلق في FIREBASE_CREDENTIALS وشغّل php artisan config:clear.');
        }

        $withToken = Contractor::whereNotNull('fcm_token')->count();
        $total     = Contractor::count();
        $this->line('');
        $this->line("مقاولون لديهم fcm_token مسجّل: <info>{$withToken}</info> من أصل {$total}");

        if ($withToken === 0) {
            $this->warn('لا يوجد أي fcm_token — تطبيق الموبايل لا يرسل الرمز عند تسجيل الدخول بعد.');
        }

        if ($membershipNumber = $this->option('to')) {
            return $this->sendTest($sender, $membershipNumber, $isLive);
        }

        return self::SUCCESS;
    }

    private function sendTest(PushSenderInterface $sender, string $membershipNumber, bool $isLive): int
    {
        $contractor = Contractor::where('membership_number', $membershipNumber)->first();

        if (! $contractor) {
            $this->error("لا يوجد مقاول برقم العضوية {$membershipNumber}");

            return self::FAILURE;
        }

        if (! $contractor->fcm_token) {
            $this->error("المقاول {$contractor->name} ليس لديه fcm_token مسجّل.");

            return self::FAILURE;
        }

        $this->line('');
        $this->line("إرسال إشعار تجريبي إلى <info>{$contractor->name}</info>...");

        $ok = $sender->send(
            $contractor->fcm_token,
            'إشعار تجريبي',
            'هذه رسالة اختبار من اتحاد المقاولين الفلسطينيين.',
            ['type' => 'test'],
        );

        if ($ok && $isLive) {
            $this->info('تم الإرسال عبر FCM بنجاح ✔');
        } elseif ($ok) {
            $this->warn('كُتب في السجل فقط (وضع log) — لم يصل أي جهاز.');
        } else {
            $this->error('فشل الإرسال — راجع storage/logs (قناة push) للتفاصيل.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
