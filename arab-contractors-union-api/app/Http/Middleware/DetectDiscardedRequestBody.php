<?php

namespace App\Http\Middleware;

use App\Support\UploadLimits;
use Closure;
use Illuminate\Http\Request;

/**
 * يكشف الطلب الذي أسقط PHP جسمه لتجاوزه post_max_size (TASK-16 #3).
 *
 * عند تجاوز الحد يُفرّغ PHP $_POST و$_FILES معاً ويُكمل التنفيذ بصمت — بلا استثناء
 * ولا تحذير يصل التطبيق. فيرى Laravel طلباً فارغاً تماماً ويُفشل التحقق بـ"الحقل
 * مطلوب" على حقول ملأها المستخدم فعلاً، وهو ما يجعل السبب الحقيقي (حجم المرفقات)
 * غير قابل للاستنتاج من الرسالة.
 *
 * ملاحظة: المتصفح يكون قد رفع الجسم كاملاً قبل أن يُسقطه PHP — لذلك التحقق من
 * الحجم في الواجهة قبل بدء الرفع هو ما يمنع الانتظار الطويل فعلياً، وهذا الفحص
 * شبكة أمان تشرح الفشل حين يصل رغم ذلك.
 */
class DetectDiscardedRequestBody
{
    public function handle(Request $request, Closure $next)
    {
        if ($this->bodyWasDiscarded($request)) {
            $maxPostMb = round(UploadLimits::maxPostKb() / 1024, 1);
            $maxFileMb = round(UploadLimits::maxFileKb() / 1024, 1);

            return response()->json([
                'status'      => false,
                'status_code' => 413,
                'message'     => "حجم المرفقات المُرسلة يتجاوز الحد الأقصى المسموح به ({$maxPostMb} ميجابايت للطلب الواحد، "
                    . "و{$maxFileMb} ميجابايت للملف الواحد). لم يصل أي من البيانات إلى الخادم — يرجى تقليل حجم الملفات أو رفعها على دفعات.",
                'errors'      => [
                    'attachments' => ['مجموع أحجام المرفقات أكبر من الحد المسموح به.'],
                ],
            ], 413);
        }

        return $next($request);
    }

    private function bodyWasDiscarded(Request $request): bool
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return false;
        }

        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength <= 0) {
            return false;
        }

        // الشرط الحاسم: وصل جسم غير فارغ على الشبكة، لكن PHP لم يُبقِ منه شيئاً.
        // يقتصر على multipart لأن JSON الخام يصل عبر php://input ولا يملأ $_POST أصلاً.
        $isMultipart = str_contains((string) $request->header('Content-Type'), 'multipart/form-data');

        return $isMultipart && empty($_POST) && empty($_FILES);
    }
}
