<?php

namespace App\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * يحوّل ملفات الصورة/المعرض المرفوعة (multipart) إلى روابط عامة داخل مصفوفة $validated.
 * بالـ update: يضيف صور gallery الجديدة فوق القديمة (بدل الاستبدال الكامل)، ويحذف من
 * القديمة أي رابط مذكور بـ remove_gallery — بالحالتين يمسح الملف الفعلي من الـ storage.
 */
trait HandlesMediaUploads
{
    private function handleMediaUploads(Request $request, array &$validated, $existing = null, string $folder = 'news', string $galleryFolder = 'news/gallery'): void
    {
        if ($request->hasFile('image')) {
            if ($existing?->image)
                $this->deletePublicFile($existing->image);

            $validated['image'] = Storage::disk('public')->url($request->file('image')->store($folder, 'public'));
        }

        $gallery = $existing?->gallery ?? [];

        if ($request->filled('remove_gallery')) {
            $toRemove = (array) $request->input('remove_gallery');
            foreach ($toRemove as $url) {
                $this->deletePublicFile($url);
            }
            $gallery = array_values(array_diff($gallery, $toRemove));
        }
        unset($validated['remove_gallery']);

        if ($request->hasFile('gallery')) {
            $newUrls = array_map(
                fn ($file) => Storage::disk('public')->url($file->store($galleryFolder, 'public')),
                $request->file('gallery'),
            );
            $gallery = array_merge($gallery, $newUrls);
        }

        if ($request->hasFile('gallery') || $request->filled('remove_gallery'))
            $validated['gallery'] = array_values($gallery);
    }

    // يحذف ملف من قرص public انطلاقاً من رابطه العام المخزَّن (image/gallery) — بصمت لو غير موجود
    private function deletePublicFile(?string $url): void
    {
        if (! $url)
            return;

        $path = Str::after(parse_url($url, PHP_URL_PATH) ?? '', '/storage/');

        if ($path && Storage::disk('public')->exists($path))
            Storage::disk('public')->delete($path);
    }
}
