<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/**
 * يُرمى عندما يرفض مزود الـ SMS إرسال رمز التحقق — بدل إخبار المستخدم "تم الإرسال"
 * وتركه ينتظر رمزاً لن يصل. يُعرض بنفس شكل أخطاء ApiResponseTrait.
 */
class SmsSendFailedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('تعذّر إرسال رمز التحقق إلى رقم جوالك. حاول مرة أخرى بعد قليل أو تواصل مع الاتحاد.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'status'      => false,
            'message'     => $this->getMessage(),
            'status_code' => 502,
            'error'       => 'sms_send_failed',
        ], 502);
    }
}
