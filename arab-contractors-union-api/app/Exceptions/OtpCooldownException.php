<?php

namespace App\Exceptions;

/**
 * يُرمى عند محاولة طلب OTP جديد قبل انتهاء فترة الانتظار (cooldown).
 */
class OtpCooldownException extends \RuntimeException
{
    public function __construct(public readonly int $expiresIn)
    {
        parent::__construct("يرجى الانتظار {$expiresIn} ثانية قبل طلب رمز جديد.");
    }
}
