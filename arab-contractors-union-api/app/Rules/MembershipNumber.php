<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * قاعدة موحّدة لهيكلية رقم العضوية — تُستخدم في كل الواجهات (إضافة مقاول، تسجيل، دخول).
 *
 *  - الأرقام القديمة:  1 حتى 927 (رقم فقط).
 *  - الأرقام الجديدة:  928_g فما فوق (رقم يليه _g).
 */
class MembershipNumber implements ValidationRule
{
    public const OLD_MAX          = 927;
    public const NEW_MIN          = 928;
    public const NEW_SUFFIX       = '_g';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid((string) $value)) {
            $fail(self::formatError());
        }
    }

    /**
     * هل الرقم صالح وفق الهيكلية المتفق عليها؟
     */
    public static function isValid(string $value): bool
    {
        $value = trim($value);

        if (preg_match('/^[0-9]+$/', $value)) {
            return (int) $value >= 1 && (int) $value <= self::OLD_MAX;
        }

        if (preg_match('/^([0-9]+)' . self::NEW_SUFFIX . '$/', $value, $matches)) {
            return (int) $matches[1] >= self::NEW_MIN;
        }

        return false;
    }

    /**
     * هل الرقم بالهيكلية الجديدة (928_g فما فوق)؟
     */
    public static function isNewFormat(string $value): bool
    {
        return (bool) preg_match(
            '/^([0-9]+)' . self::NEW_SUFFIX . '$/',
            trim($value),
        );
    }

    public static function formatError(): string
    {
        return 'صيغة رقم العضوية غير صحيحة. يجب أن يكون رقماً قديماً (1-'
            . self::OLD_MAX . ') أو رقماً جديداً يليه '
            . self::NEW_SUFFIX . ' (مثال: ' . self::NEW_MIN . self::NEW_SUFFIX . ').';
    }
}
