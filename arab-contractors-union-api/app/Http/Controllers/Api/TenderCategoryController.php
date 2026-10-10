<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Tender;
use App\Models\TenderCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * إدارة تصنيفات العطاءات + صورة افتراضية لكل تصنيف.
 *
 * tenders.category يحمل اسم التصنيف (لا id) — لذلك إعادة التسمية تُعمَّم على العطاءات
 * بنفس المعاملة، والحذف مرفوض ما دام التصنيف مستخدَماً (التعطيل هو البديل: يخفيه من
 * منتقي الإنشاء/التعديل ويُبقي العطاءات القديمة سليمة).
 */
class TenderCategoryController extends Controller
{
    use ApiResponseTrait;

    // GET /api/v1/tender-categories — ?active_only=1 لمنتقي نموذج العطاء
    public function index(Request $request)
    {
        $query = TenderCategory::ordered()->withCount('tenders');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return $this->success($query->get());
    }

    // GET /api/v1/tender-categories-public — للتطبيق/الموقع: الفعّالة فقط، بلا عدّادات
    public function publicIndex()
    {
        return $this->success(
            TenderCategory::where('is_active', true)->ordered()->get(['id', 'name', 'image_path'])
                ->map(fn (TenderCategory $c) => ['id' => $c->id, 'name' => $c->name, 'image_url' => $c->image_url])
        );
    }

    // GET /api/v1/contractor/tenders/categories — تطبيق المقاول (موثَّق): الفعّالة بالترتيب
    // مع عدد العطاءات المفتوحة (status=open وغير مؤرشفة) وعدد الفعّالة (غير مؤرشفة) بكل تصنيف.
    // قيمة الفلتر على GET /contractor/tenders هي ?category={name} أو ?category_id={id}.
    public function contractorIndex()
    {
        $categories = TenderCategory::where('is_active', true)->ordered()
            ->withCount([
                'tenders as open_tenders_count'   => fn ($q) => $q->where('status', 'open')->whereNull('archived_at'),
                'tenders as active_tenders_count' => fn ($q) => $q->whereNull('archived_at'),
            ])
            ->get();

        return $this->success($categories->map(fn (TenderCategory $c) => [
            'id'                   => $c->id,
            'name'                 => $c->name,
            'value'                => $c->name,
            'image_url'            => $c->image_url,
            'sort_order'           => $c->sort_order,
            'open_tenders_count'   => (int) $c->open_tenders_count,
            'active_tenders_count' => (int) $c->active_tenders_count,
        ])->values());
    }

    // POST /api/v1/tender-categories (multipart)
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100|unique:tender_categories,name',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active'  => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        $category = TenderCategory::create([
            'name'       => trim($data['name']),
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('tenders/category-images', 'public')
                : null,
            'is_active'  => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) TenderCategory::max('sort_order') + 1),
        ]);

        AuditLogService::record(Auth::user(), 'tender_category.created', $category, ['name' => $category->name]);

        return $this->success($category->loadCount('tenders'), 'تمت إضافة التصنيف بنجاح.', 201);
    }

    // POST /api/v1/tender-categories/{tenderCategory} مع _method=PATCH (multipart لأجل الصورة)
    public function update(Request $request, TenderCategory $tenderCategory)
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'required', 'string', 'max:100', Rule::unique('tender_categories', 'name')->ignore($tenderCategory->id)],
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_image' => 'sometimes|boolean',
            'is_active'    => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer|min:0',
        ]);

        $oldName  = $tenderCategory->name;
        $oldImage = $tenderCategory->image_path;
        $newImage = $request->hasFile('image')
            ? $request->file('image')->store('tenders/category-images', 'public')
            : null;

        DB::transaction(function () use ($tenderCategory, $data, $request, $oldName, $newImage) {
            if (isset($data['name'])) {
                $tenderCategory->name = trim($data['name']);
            }
            if (array_key_exists('is_active', $data)) {
                $tenderCategory->is_active = $data['is_active'];
            }
            if (array_key_exists('sort_order', $data)) {
                $tenderCategory->sort_order = $data['sort_order'];
            }
            if ($newImage) {
                $tenderCategory->image_path = $newImage;
            } elseif ($request->boolean('remove_image')) {
                $tenderCategory->image_path = null;
            }

            $tenderCategory->save();

            // withTrashed: عطاء محذوف ناعماً ثم مُستعاد يجب أن يبقى على التصنيف الصحيح.
            // toBase() يتجاوز أحداث Tender::updating عمداً — تغيير اسم التصنيف ليس "تحديثاً"
            // لمحتوى العطاء فلا يجب أن يحوّل display_status إلى "محدَّث".
            if ($tenderCategory->name !== $oldName) {
                Tender::withTrashed()->where('category', $oldName)->toBase()
                    ->update(['category' => $tenderCategory->name]);
            }
        });

        if ($oldImage && $oldImage !== $tenderCategory->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        AuditLogService::record(Auth::user(), 'tender_category.updated', $tenderCategory, ['name' => $tenderCategory->name]);

        return $this->success($tenderCategory->fresh()->loadCount('tenders'), 'تم تحديث التصنيف بنجاح.');
    }

    // DELETE /api/v1/tender-categories/{tenderCategory}
    public function destroy(TenderCategory $tenderCategory)
    {
        if (Tender::withTrashed()->where('category', $tenderCategory->name)->exists()) {
            return $this->error('لا يمكن حذف هذا التصنيف لأنه مرتبط بعطاءات موجودة — يمكنك تعطيله بدلاً من ذلك.', 422);
        }

        if ($tenderCategory->image_path) {
            Storage::disk('public')->delete($tenderCategory->image_path);
        }

        AuditLogService::record(Auth::user(), 'tender_category.deleted', $tenderCategory, ['name' => $tenderCategory->name]);

        $tenderCategory->delete();

        return $this->success(message: 'تم حذف التصنيف بنجاح.');
    }
}
