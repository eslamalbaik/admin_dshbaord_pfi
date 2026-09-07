<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * يحوّل ملفات الصورة/المعرض المرفوعة (multipart) إلى مسارات نسبية داخل مصفوفة $validated
 * (لا روابط كاملة — الرابط العام يُبنى وقت القراءة عبر accessor بالموديل، فلا يتجمّد على
 * APP_URL وقت الرفع؛ نفس مشكلة اختلاف الدومين بين وقت الرفع ووقت العرض تُحَل جذرياً).
 * بالـ update: يضيف صور gallery الجديدة فوق القديمة (بدل الاستبدال الكامل)، ويحذف من
 * القديمة أي رابط مذكور بـ remove_gallery — بالحالتين يمسح الملف الفعلي من الـ storage.
 */
trait HandlesMediaUploads
{
    private function handleMediaUploads(Request $request, array &$validated, $existing = null, string $folder = 'news', string $galleryFolder = 'news/gallery'): void
    {
        if ($request->hasFile('image')) {
            if ($existing?->getRawOriginal('image'))
                $this->deletePublicFile($existing->getRawOriginal('image'));

            $validated['image'] = $request->file('image')->store($folder, 'public');
        }

        $gallery = $existing?->getRawOriginal('gallery');
        $gallery = $gallery ? (json_decode($gallery, true) ?? []) : [];

        if ($request->filled('remove_gallery')) {
            // القيم الواردة من الفرونت روابط كاملة (كما تُعرض) — نحوّلها لمسارات نسبية للمقارنة والحذف
            $toRemove = array_map([$this, 'urlToRelativePath'], (array) $request->input('remove_gallery'));
            foreach ($toRemove as $path) {
                $this->deletePublicFile($path);
            }
            $gallery = array_values(array_diff($gallery, $toRemove));
        }
        unset($validated['remove_gallery']);

        if ($request->hasFile('gallery')) {
            $newPaths = array_map(
                fn ($file) => $file->store($galleryFolder, 'public'),
                $request->file('gallery'),
            );
            $gallery = array_merge($gallery, $newPaths);
        }

        if ($request->hasFile('gallery') || $request->filled('remove_gallery'))
            $validated['gallery'] = array_values($gallery);
    }

    // يحوّل رابطاً عاماً كاملاً (أو مساراً نسبياً أصلاً) إلى مسار نسبي داخل قرص public
    private function urlToRelativePath(string $value): string
    {
        if (! str_contains($value, '/storage/'))
            return $value; // مسار نسبي أصلاً

        return Str::after(parse_url($value, PHP_URL_PATH) ?? '', '/storage/');
    }

    // يحذف ملف من قرص public انطلاقاً من مساره (نسبي أو رابط كامل قديم) — بصمت لو غير موجود
    private function deletePublicFile(?string $value): void
    {
        if (! $value)
            return;

        $path = $this->urlToRelativePath($value);

        if ($path && Storage::disk('public')->exists($path))
            Storage::disk('public')->delete($path);
    }
}
