<?php

namespace App\Support;

/**
 * سياق مؤقت لسجل التغييرات: لما الإدارة بتعتمد/بترجّع طلب مقاول، التغيير بيتسجّل
 * باسم الموظف لكن مربوط بالطلب ومع ملاحظة («اعتماد تعديل المقاول»).
 */
class FieldHistoryContext
{
    private static ?array $current = null;

    public static function run(array $context, callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = $context;

        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }

    public static function get(): ?array
    {
        return self::$current;
    }
}
