<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait GeneratesSlug
{
    public static function generateSlug(string $title): string
    {
        $slug = Str::slug($title, '-');
        $count = static::where('slug', 'like', "{$slug}%")->count();

        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }
}
