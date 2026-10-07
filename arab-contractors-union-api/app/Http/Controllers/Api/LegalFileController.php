<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\LegalFile;
use App\Models\LegalFileCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LegalFileController extends Controller
{
    use ApiResponseTrait;

    // ─── Category labels (DB-backed, admin-editable) ─────────────────────────
    private ?array $labelCache = null;

    private function categoryLabels(): array
    {
        return $this->labelCache ??= LegalFileCategory::orderBy('sort')->orderBy('id')->pluck('label', 'key')->all();
    }

    // ─── Helper: place a file at a given position and re-number the rest ─────
    // الترتيب يبدأ من 0؛ باقي الملفات تُزاح وتُرقَّم 0..n-1 بدون تكرار.
    private function placeAt(LegalFile $target, int $position): void
    {
        $others = LegalFile::where('id', '!=', $target->id)
            ->orderBy('sort')->orderBy('id')->get()->values();

        $position = max(0, min($position, $others->count()));
        $ordered  = $others->all();
        array_splice($ordered, $position, 0, [$target]);

        foreach ($ordered as $i => $f) {
            if ((int) $f->sort !== $i) {
                $f->forceFill(['sort' => $i])->saveQuietly();
            }
        }
    }

    // ─── Helper: format a LegalFile for API response ─────────────────────────
    private function format(LegalFile $f): array
    {
        return [
            'id'             => $f->id,
            'title'          => $f->title,
            'title_en'       => $f->title_en,
            'description'    => $f->description,
            'description_en' => $f->description_en,
            'category'       => $f->category,
            'category_label' => $f->category === null ? 'بدون تصنيف' : ($this->categoryLabels()[$f->category] ?? $f->category),
            'url'            => $f->url,
            'mime_type'      => $f->mime_type,
            'size'           => $f->size,
            'formatted_size' => $f->formatted_size,
            'sort'           => $f->sort,
            'is_active'      => $f->is_active,
            'is_featured'    => $f->is_featured,
            'created_at'     => $f->created_at,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Public endpoint — mobile app (no auth required)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/legal-files?category=legislation&search=...
     * شاشة "المكتبة القانونية" — بحث + تبويبات تصنيف + عداد لكل تبويب + قسم "الأكثر طلباً".
     * category=all أو بدون تمريره: بدون فلترة تصنيف (لكن "الكل" بالنتيجة).
     */
    public function publicIndex(Request $request)
    {
        $hasCategory = $request->filled('category') && $request->category !== 'all';

        $query = LegalFile::where('is_active', true);

        if ($hasCategory) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($qb) => $qb->where('title', 'like', "%{$q}%")
                ->orWhere('title_en', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        $files = $query->orderByDesc('is_featured')->orderBy('sort')->orderBy('id')
            ->get()->map(fn($f) => $this->format($f))->values();

        $counts = LegalFile::where('is_active', true)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        $categories = collect($this->categoryLabels())->map(fn($label, $cat) => [
            'value' => $cat,
            'label' => $label,
            'count' => (int) ($counts[$cat] ?? 0),
        ])->values();

        return $this->success([
            'total'      => (int) $counts->sum(),
            'categories' => $categories,
            'featured'   => $files->where('is_featured', true)->values(),
            'files'      => $files,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Admin Dashboard — Protected (Sanctum)
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/dashboard/legal-files */
    public function index(Request $request)
    {
        $query = LegalFile::orderBy('sort')->orderBy('id');

        if ($request->category === 'none') {
            $query->whereNull('category');
        } elseif ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn($qb) =>
                $qb->where('title', 'like', "%{$q}%")
                   ->orWhere('title_en', 'like', "%{$q}%")
            );
        }

        $paginator = $query->paginate(20)->through(fn($f) => $this->format($f));

        return $this->paginated($paginator);
    }

    /** POST /api/v1/dashboard/legal-files */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:300',
            'title_en'       => 'nullable|string|max:300',
            'description'    => 'nullable|string|max:1000',
            'description_en' => 'nullable|string|max:1000',
            'category'       => 'nullable|exists:legal_file_categories,key',
            'sort'           => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
            'is_featured'    => 'boolean',
            'file'           => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:51200', // 50 MB max
        ]);

        $file      = $request->file('file');
        $filePath  = $file->store('legal-files/' . ($data['category'] ?? 'uncategorized'), 'public');

        $legalFile = LegalFile::create([
            'title'          => $data['title'],
            'title_en'       => $data['title_en']       ?? null,
            'description'    => $data['description']    ?? null,
            'description_en' => $data['description_en'] ?? null,
            'category'       => $data['category'] ?? null,
            'file_path'      => $filePath,
            'disk'           => 'public',
            'mime_type'      => $file->getMimeType(),
            'size'           => $file->getSize(),
            'sort'           => $data['sort'] ?? 0,
            'is_active'      => $data['is_active'] ?? true,
            'is_featured'    => $data['is_featured'] ?? false,
            'uploaded_by'    => Auth::id(),
        ]);

        if (array_key_exists('sort', $data) && $data['sort'] !== null) {
            $this->placeAt($legalFile, (int) $data['sort']);
        }

        AuditLogService::record(Auth::user(), 'legal_file.created', $legalFile, ['title' => $legalFile->title, 'category' => $legalFile->category]);

        return $this->success($this->format($legalFile), 'تم رفع الملف بنجاح.', 201);
    }

    /** POST /api/v1/dashboard/legal-files/{legalFile} (with _method=PUT for multipart) */
    public function update(Request $request, LegalFile $legalFile)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:300',
            'title_en'       => 'nullable|string|max:300',
            'description'    => 'nullable|string|max:1000',
            'description_en' => 'nullable|string|max:1000',
            'category'       => 'nullable|exists:legal_file_categories,key',
            'sort'           => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
            'is_featured'    => 'boolean',
            'file'           => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:51200',
        ]);

        // Replace file if a new one is uploaded
        if ($request->hasFile('file')) {
            Storage::disk($legalFile->disk)->delete($legalFile->file_path);
            $file     = $request->file('file');
            $filePath = $file->store('legal-files/' . ($data['category'] ?? 'uncategorized'), 'public');
            $legalFile->file_path = $filePath;
            $legalFile->mime_type = $file->getMimeType();
            $legalFile->size      = $file->getSize();
        }

        $legalFile->update([
            'title'          => $data['title'],
            'title_en'       => $data['title_en']       ?? null,
            'description'    => $data['description']    ?? null,
            'description_en' => $data['description_en'] ?? null,
            'category'       => $data['category'] ?? null,
            'is_active'      => $data['is_active'] ?? $legalFile->is_active,
            'is_featured'    => $data['is_featured'] ?? $legalFile->is_featured,
        ]);

        if (array_key_exists('sort', $data) && $data['sort'] !== null) {
            $this->placeAt($legalFile, (int) $data['sort']);
        }

        AuditLogService::record(Auth::user(), 'legal_file.updated', $legalFile, ['title' => $legalFile->title]);

        return $this->success($this->format($legalFile->fresh()), 'تم تحديث الملف بنجاح.');
    }

    /** DELETE /api/v1/dashboard/legal-files/{legalFile} */
    public function destroy(LegalFile $legalFile)
    {
        AuditLogService::record(Auth::user(), 'legal_file.deleted', $legalFile, ['title' => $legalFile->title]);

        Storage::disk($legalFile->disk)->delete($legalFile->file_path);
        $legalFile->delete();

        return $this->success(message: 'تم حذف الملف بنجاح.');
    }
}
