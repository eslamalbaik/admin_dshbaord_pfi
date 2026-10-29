<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * مقدمة دولة لخانات الجوال (970 فلسطين…) مع قاعدة الرقم المحلي بعدها.
 * الأدمن بيضيف ويعدّل من الإعدادات؛ "الحذف" = إخفاء فقط حتى تضل الأرقام المحفوظة مفهومة.
 */
class CountryDialCode extends Model
{
    protected $fillable = [
        'name', 'iso_code', 'dial_code', 'flag_path', 'min_digits', 'max_digits',
        'starts_with', 'sort', 'is_active', 'is_default',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
        'min_digits' => 'integer',
        'max_digits' => 'integer',
        'sort'       => 'integer',
    ];

    protected $appends = ['flag_url'];

    /** العلم: المرفوع يدوياً، وإلا صورة علم الدولة من رمزها (الإيموجي ما بتظهر أعلام على ويندوز). */
    public function getFlagUrlAttribute(): ?string
    {
        if ($this->flag_path) {
            return Storage::disk('public')->url($this->flag_path);
        }

        return $this->iso_code ? 'https://flagcdn.com/w40/' . strtolower($this->iso_code) . '.png' : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** هل الرقم المحلي (بعد المقدمة) مطابق لقاعدة هالدولة؟ */
    public function acceptsNational(string $national): bool
    {
        $len = strlen($national);
        if (! ctype_digit($national) || $len < $this->min_digits || $len > $this->max_digits) {
            return false;
        }

        $prefixes = array_filter(array_map('trim', explode(',', (string) $this->starts_with)), 'strlen');

        return ! $prefixes || collect($prefixes)->contains(fn ($p) => str_starts_with($national, $p));
    }

    /**
     * التحقق من رقم جوال بأي صيغة: +970599123456 / 00970… / 970599123456 (مخزّن) / 0599123456 / 599123456.
     * الرقم بدون مقدمة دولية بيتفحص على المقدمة الافتراضية (فلسطين).
     */
    public static function isValidPhone(string $value, ?Collection $codes = null): bool
    {
        $raw = trim($value);
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '' || preg_match('/[^\d+\s\-()]/', $raw)) {
            return false;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $codes ??= self::active()->get();
        $codes = $codes->sortByDesc(fn ($c) => strlen($c->dial_code));

        if ($international || ! str_starts_with($digits, '0')) {
            foreach ($codes as $code) {
                if (str_starts_with($digits, $code->dial_code)) {
                    $national = substr($digits, strlen($code->dial_code));
                    // +970 0599… ← الصفر المحلي بعد المقدمة مقبول
                    if ($code->acceptsNational($national) || (str_starts_with($national, '0') && $code->acceptsNational(substr($national, 1)))) {
                        return true;
                    }
                }
            }
            if ($international) {
                return false;
            }
        }

        $default = $codes->firstWhere('is_default', true) ?? $codes->firstWhere('dial_code', '970');
        if (! $default) {
            return (bool) preg_match('/^[0-9]{9,15}$/', $digits);
        }

        return $default->acceptsNational(str_starts_with($digits, '0') ? substr($digits, 1) : $digits);
    }
}
