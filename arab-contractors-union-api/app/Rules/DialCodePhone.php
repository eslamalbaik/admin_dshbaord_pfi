<?php

namespace App\Rules;

use App\Models\CountryDialCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** رقم جوال مطابق لإحدى مقدمات الدول المفعّلة وقاعدة طولها (إعدادات ← مقدمات الدول). */
class DialCodePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! CountryDialCode::isValidPhone($value)) {
            $fail(':attribute غير صحيح: تأكد من مقدمة الدولة وعدد الأرقام (فلسطين: 9 أرقام تبدأ بـ 5، مثال 599123456).');
        }
    }
}
