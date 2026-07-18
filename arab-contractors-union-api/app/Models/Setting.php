<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public $timestamps = true;

    /** قراءة قيمة إعداد مع قيمة افتراضية (مع تخزين مؤقت) */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', function () {
            return static::pluck('value', 'key')->toArray();
        });

        return $all[$key] ?? $default;
    }

    /** ضبط قيمة إعداد */
    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget('settings.all');
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('settings.all'));
        static::deleted(fn () => Cache::forget('settings.all'));
    }
}
