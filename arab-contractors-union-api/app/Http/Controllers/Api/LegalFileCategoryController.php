<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\LegalFile;
use App\Models\LegalFileCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LegalFileCategoryController extends Controller
{
    use ApiResponseTrait;

    private function format(LegalFileCategory $c): array
    {
        return [
            'id'          => $c->id,
            'key'         => $c->key,
            'label'       => $c->label,
            'label_en'    => $c->label_en,
            'sort'        => $c->sort,
            'is_system'   => $c->is_system,
            'files_count' => LegalFile::where('category', $c->key)->count(),
        ];
    }

    /** GET /api/v1/dashboard/legal-file-categories */
    public function index()
    {
        $items = LegalFileCategory::orderBy('sort')->orderBy('id')->get()
            ->map(fn($c) => $this->format($c))->values();

        return $this->success($items);
    }

    /** POST /api/v1/dashboard/legal-file-categories */
    public function store(Request $request)
    {
        $data = $request->validate([
            'label'    => 'required|string|max:100',
            'label_en' => 'nullable|string|max:100',
        ]);

        $category = LegalFileCategory::create([
            'key'       => 'c_' . strtolower(Str::random(10)),
            'label'     => $data['label'],
            'label_en'  => $data['label_en'] ?? null,
            'sort'      => (int) LegalFileCategory::max('sort') + 1,
            'is_system' => false,
        ]);

        AuditLogService::record(Auth::user(), 'legal_file_category.created', $category, ['label' => $category->label]);

        return $this->success($this->format($category), 'تمت إضافة التصنيف.', 201);
    }

    /** POST /api/v1/dashboard/legal-file-categories/{legalFileCategory} */
    public function update(Request $request, LegalFileCategory $legalFileCategory)
    {
        $data = $request->validate([
            'label'    => 'required|string|max:100',
            'label_en' => 'nullable|string|max:100',
        ]);

        $legalFileCategory->update([
            'label'    => $data['label'],
            'label_en' => $data['label_en'] ?? null,
        ]);

        AuditLogService::record(Auth::user(), 'legal_file_category.updated', $legalFileCategory, ['label' => $legalFileCategory->label]);

        return $this->success($this->format($legalFileCategory->fresh()), 'تم تحديث التصنيف.');
    }

    /** DELETE /api/v1/dashboard/legal-file-categories/{legalFileCategory} */
    public function destroy(LegalFileCategory $legalFileCategory)
    {
        if (LegalFile::where('category', $legalFileCategory->key)->exists()) {
            return $this->error('لا يمكن حذف تصنيف يحتوي ملفات. انقل الملفات أو احذفها أولاً.', 422);
        }

        AuditLogService::record(Auth::user(), 'legal_file_category.deleted', $legalFileCategory, ['label' => $legalFileCategory->label]);
        $legalFileCategory->delete();

        return $this->success(message: 'تم حذف التصنيف.');
    }
}
