<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * قاعدة موحّدة لهيكلية رقم العضوية — تُستخدم في كل الواجهات (إضافة مقاول، تسجيل، دخول).
 *
 * كل أرقام العضوية بصيغة رقم يليه _g (مثال: 184_g، 932_g) — بما فيها الأرقام القديمة
 * 1-927 التي كانت تُكتب رقماً فقط قبل توحيدها (migration 2026_09_29_120000).
 * الأرقام الجديدة تُولَّد من 928_g فما فوق (Contractor::nextMembershipNumber).
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
        return (bool) preg_match('/^([0-9]+)' . self::NEW_SUFFIX . '$/', trim($value), $matches)
            && (int) $matches[1] >= 1;
    }

    /**
     * هل الرقم بالهيكلية الجديدة (رقم يليه _g)؟
     */
    public static function isNewFormat(string $value): bool
    {
        return (bool) preg_match(
            '/^([0-9]+)' . self::NEW_SUFFIX . '$/',
            trim($value),
        );
    }

    /**
     * يُكمل الرقم المكتوب بدون لاحقة ("184" ← "184_g") — المقاولون القدامى اعتادوا
     * كتابة رقمهم بدون _g، فيُقبل منهم عند الدخول والبحث.
     */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? $value . self::NEW_SUFFIX : $value;
    }

    public static function formatError(): string
    {
        return 'صيغة رقم العضوية غير صحيحة. يجب أن يكون رقماً يليه '
            . self::NEW_SUFFIX . ' (مثال: 184' . self::NEW_SUFFIX . ').';
    }
}
