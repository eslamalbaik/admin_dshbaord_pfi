<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\LegalFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LegalFileController extends Controller
{
    use ApiResponseTrait;

    // ─── Label maps ──────────────────────────────────────────────────────────
    private array $categoryLabels = [
        'legislation' => 'تشريعات',
        'mou'         => 'مذكرات تفاهم',
        'other'       => 'أخرى',
    ];

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
            'category_label' => $this->categoryLabels[$f->category] ?? $f->category,
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

        $categories = collect($this->categoryLabels)->map(fn($label, $cat) => [
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

        if ($request->filled('category')) {
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
            'category'       => 'required|in:legislation,mou,other',
            'sort'           => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
            'is_featured'    => 'boolean',
            'file'           => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:51200', // 50 MB max
        ]);

        $file      = $request->file('file');
        $filePath  = $file->store('legal-files/' . $data['category'], 'public');

        $legalFile = LegalFile::create([
            'title'          => $data['title'],
            'title_en'       => $data['title_en']       ?? null,
            'description'    => $data['description']    ?? null,
            'description_en' => $data['description_en'] ?? null,
            'category'       => $data['category'],
            'file_path'      => $filePath,
            'disk'           => 'public',
            'mime_type'      => $file->getMimeType(),
            'size'           => $file->getSize(),
            'sort'           => $data['sort'] ?? 0,
            'is_active'      => $data['is_active'] ?? true,
            'is_featured'    => $data['is_featured'] ?? false,
            'uploaded_by'    => Auth::id(),
        ]);

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
            'category'       => 'required|in:legislation,mou,other',
            'sort'           => 'nullable|integer|min:0',
            'is_active'      => 'boolean',
            'is_featured'    => 'boolean',
            'file'           => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:51200',
        ]);

        // Replace file if a new one is uploaded
        if ($request->hasFile('file')) {
            Storage::disk($legalFile->disk)->delete($legalFile->file_path);
            $file     = $request->file('file');
            $filePath = $file->store('legal-files/' . $data['category'], 'public');
            $legalFile->file_path = $filePath;
            $legalFile->mime_type = $file->getMimeType();
            $legalFile->size      = $file->getSize();
        }

        $legalFile->update([
            'title'          => $data['title'],
            'title_en'       => $data['title_en']       ?? null,
            'description'    => $data['description']    ?? null,
            'description_en' => $data['description_en'] ?? null,
            'category'       => $data['category'],
            'sort'           => $data['sort'] ?? $legalFile->sort,
            'is_active'      => $data['is_active'] ?? $legalFile->is_active,
            'is_featured'    => $data['is_featured'] ?? $legalFile->is_featured,
        ]);

        return $this->success($this->format($legalFile->fresh()), 'تم تحديث الملف بنجاح.');
    }

    /** DELETE /api/v1/dashboard/legal-files/{legalFile} */
    public function destroy(LegalFile $legalFile)
    {
        Storage::disk($legalFile->disk)->delete($legalFile->file_path);
        $legalFile->delete();

        return $this->success(message: 'تم حذف الملف بنجاح.');
    }
}
