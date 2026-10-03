<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait GeneratesSlug
{
    /**
     * slug فريد فعلياً على مستوى الجدول. عمود slug عليه unique index يشمل الصفوف المحذوفة
     * (SoftDeletes) — العدّ القديم (`like slug%` بدون withTrashed) ما كان يشوفها، فإعادة
     * استخدام عنوان فعالية/خبر محذوف كانت ترجّع slug مكرر → خطأ 500 (Integrity constraint).
     * $ignoreId: عند التعديل، الصف نفسه ما بيُحسب تكراراً لحاله.
     */
    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title, '-') ?: Str::lower(Str::random(8));

        $exists = function (string $slug) use ($ignoreId): bool {
            $query = in_array(SoftDeletes::class, class_uses_recursive(static::class))
                ? static::withTrashed()
                : static::query();

            return $query->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists();
        };

        $slug = $base;
        for ($i = 1; $exists($slug); $i++)
            $slug = "{$base}-{$i}";

        return $slug;
    }
}
