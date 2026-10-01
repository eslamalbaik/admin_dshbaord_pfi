<?php

namespace App\Models\Concerns;

/**
 * يُخزَّن مسار نسبي داخل قرص public (لا رابط كامل) — الرابط العام يُبنى هنا وقت القراءة
 * عبر asset()، فيعكس دومين APP_URL الحالي دوماً بدل ما يتجمّد على قيمته وقت الرفع.
 *
 * ملاحظة: الموديل المستخدِم يجب ألّا يضع cast على `gallery` (الـ accessor هنا يتولّى
 * فك الـ JSON، والـ mutator يتولّى ترميزه).
 */
trait HasPublicMediaUrls
{
    public function getImageAttribute(?string $value): ?string
    {
        return $value ? $this->publicMediaUrl($value) : null;
    }

    /** @return string[] */
    public function getGalleryAttribute(?string $value): array
    {
        $paths = $value ? (json_decode($value, true) ?? []) : [];

        return array_map(fn ($path) => $this->publicMediaUrl($path), $paths);
    }

    /** @param string[]|null $value مصفوفة مسارات نسبية — تُخزَّن JSON */
    public function setGalleryAttribute(?array $value): void
    {
        $this->attributes['gallery'] = $value ? json_encode(array_values($value)) : null;
    }

    /** صفوف قديمة خزّنت الرابط كاملاً — تُعاد كما هي بدل تكرار بادئة /storage/ */
    private function publicMediaUrl(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : asset('storage/' . $path);
    }
}
