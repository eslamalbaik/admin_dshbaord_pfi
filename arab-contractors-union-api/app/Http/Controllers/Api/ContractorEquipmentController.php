<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Equipment;
use App\Models\EquipmentImage;
use App\Models\EquipmentPackage;
use App\Models\EquipmentReport;
use App\Models\EquipmentType;
use App\Models\Governorate;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * سوق الآليات — واجهة المقاول (REQ-04 → REQ-08): "آلياتي" (CRUD ذاتي) +
 * تصفح السوق العام + الإبلاغ عن مشكلة + حالة اشتراك الباقات.
 */
class ContractorEquipmentController extends Controller
{
    use ApiResponseTrait;

    private function format(Equipment $e): array
    {
        return [
            'id'                => $e->id,
            'contractor_id'     => $e->contractor_id,
            // بطاقة "المالك/المقاول" بشاشة تفاصيل الألية — زر تواصل واتساب مباشر (owner_phone)
            'contractor_name'   => $e->contractor?->name,
            'type'              => $e->type?->only(['id', 'name_ar', 'icon']),
            'name'              => $e->name,
            'brand'             => $e->brand,
            'description'       => $e->description,
            'manufacture_year'  => $e->manufacture_year,
            'power'             => $e->power,
            'condition'         => $e->condition,
            'contract_type'     => $e->contract_type,
            'governorate'       => $e->governorate,
            'city'              => $e->city,
            'daily_price'       => $e->daily_price,
            'owner_phone'       => $e->owner_phone,
            'status'            => $e->status,
            'is_featured'       => (bool) $e->is_featured,
            'needs_maintenance' => (bool) $e->needs_maintenance,
            'is_new'            => $e->is_new,
            'is_hidden'         => (bool) $e->is_hidden,
            'images'            => $e->images->map(fn ($img) => [
                'id'         => $img->id,
                'url'        => Storage::disk('public')->url($img->path),
                'is_primary' => $img->is_primary,
            ])->values(),
            'created_at'        => $e->created_at,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  "آلياتي" — إدارة إعلانات المقاول الخاصة به
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/equipment/form-options
    // كل خيارات نموذج "إضافة/تعديل آلية" بنداء واحد (REQ: تجميع القوائم المنسدلة)
    public function formOptions()
    {
        return $this->success([
            'equipment_types' => EquipmentType::where('is_active', true)
                ->orderBy('name_ar')
                ->get(['id', 'name_ar', 'name_en', 'icon']),
            'conditions' => [
                ['value' => 'excellent', 'label' => 'ممتازة'],
                ['value' => 'good',      'label' => 'جيدة'],
                ['value' => 'fair',      'label' => 'بحاجة صيانة'],
            ],
            'contract_types' => [
                ['value' => 'daily',   'label' => 'يومي'],
                ['value' => 'weekly',  'label' => 'اسبوعي'],
                ['value' => 'monthly', 'label' => 'شهري'],
            ],
            // قائمة ماركات شائعة للاختيار السريع — الحقل نفسه نص حر، "أخرى" يسمح بإدخال أي قيمة
            'brands' => [
                'Caterpillar', 'Komatsu', 'Volvo', 'JCB', 'Hitachi',
                'Liebherr', 'Hyundai', 'Case', 'Bobcat', 'John Deere',
            ],
            'governorates' => Governorate::with('cities')
                ->orderBy('sort')->orderBy('id')
                ->get()
                ->map(fn ($g) => [
                    'id'     => $g->id,
                    'name'   => $g->name,
                    'cities' => $g->cities->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                ])->values(),
        ]);
    }

    // GET /api/v1/contractor/equipment?hidden=1
    public function index(Request $request)
    {
        $query = $request->user()->equipment()->with(['type', 'images']);

        if ($request->boolean('hidden')) {
            $query->where('is_hidden', true);
        } elseif ($request->has('hidden')) {
            $query->where('is_hidden', false);
        }

        $paginator = $query->latest()
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($e) => $this->format($e));

        return $this->paginated($paginator);
    }

    // POST /api/v1/contractor/equipment
    public function store(Request $request)
    {
        $contractor = $request->user();

        if ($contractor->equipment_banned_at) {
            return $this->error('تم إيقافك من النشر بسوق الآليات لمخالفة الشروط — يرجى مراجعة الاتحاد.', 403);
        }

        if ($contractor->outstandingDuesTotal() > 0) {
            return $this->error('لا يمكن إضافة آلية جديدة قبل تسوية الذمم المالية المستحقّة.', 403, null, 'dues_pending');
        }

        $data = $request->validate([
            'equipment_type_id'  => 'required|exists:equipment_types,id',
            'name'               => 'required|string|max:255',
            'brand'              => 'nullable|string|max:100',
            'description'        => 'nullable|string',
            'manufacture_year'   => 'nullable|integer|min:1970|max:' . date('Y'),
            'power'              => 'nullable|string|max:50',
            'condition'          => 'nullable|in:excellent,good,fair',
            'contract_type'      => 'required|in:daily,weekly,monthly',
            'governorate'        => 'nullable|string|max:100',
            'city'               => 'nullable|string|max:100',
            'daily_price'        => 'required|numeric|min:0',
            'owner_phone'        => 'nullable|string|max:20',
            'accept_disclaimer'  => 'sometimes|boolean',
            'images'             => 'nullable|array|max:8',
            'images.*'           => 'image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        if (! $contractor->equipment_disclaimer_accepted_at) {
            if (empty($data['accept_disclaimer'])) {
                return $this->error(
                    'يجب الموافقة على إقرار إخلاء المسؤولية قبل نشر أول إعلان.',
                    403,
                    null,
                    'disclaimer_required',
                );
            }
            $contractor->update(['equipment_disclaimer_accepted_at' => now()]);
        }

        if (! $this->hasActiveMarketplaceAccess($contractor)) {
            return $this->error(
                'انتهت الفترة التجريبية المجانية لسوق الآليات — يرجى الاشتراك بإحدى الباقات لمتابعة النشر.',
                402,
                null,
                'subscription_required',
            );
        }

        $images = $request->file('images', []);
        unset($data['images'], $data['accept_disclaimer']);

        $equipment = $contractor->equipment()->create($data + ['status' => 'visible']);

        foreach ($images as $index => $file) {
            $path = $file->store('equipment/' . $equipment->id, 'public');
            EquipmentImage::create([
                'equipment_id' => $equipment->id,
                'path'         => $path,
                'is_primary'   => $index === 0,
                'sort_order'   => $index,
            ]);
        }

        return $this->success($this->format($equipment->fresh(['type', 'images'])), 'تم نشر إعلان الآلية بنجاح.', 201);
    }

    // PATCH /api/v1/contractor/equipment/{equipment}
    public function update(Request $request, Equipment $equipment)
    {
        if ($equipment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بتعديل هذا الإعلان.', 403);
        }

        $data = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'brand'             => 'nullable|string|max:100',
            'description'       => 'nullable|string',
            'manufacture_year'  => 'nullable|integer|min:1970|max:' . date('Y'),
            'power'             => 'nullable|string|max:50',
            'condition'         => 'nullable|in:excellent,good,fair',
            'contract_type'     => 'sometimes|in:daily,weekly,monthly',
            'governorate'       => 'nullable|string|max:100',
            'city'              => 'nullable|string|max:100',
            'daily_price'       => 'sometimes|numeric|min:0',
            'owner_phone'       => 'nullable|string|max:20',
            'is_hidden'         => 'sometimes|boolean',
            'needs_maintenance' => 'sometimes|boolean',
        ]);

        $equipment->update($data);

        return $this->success($this->format($equipment->fresh(['type', 'images'])), 'تم تحديث الإعلان بنجاح.');
    }

    // DELETE /api/v1/contractor/equipment/{equipment}
    public function destroy(Request $request, Equipment $equipment)
    {
        if ($equipment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بحذف هذا الإعلان.', 403);
        }

        foreach ($equipment->images as $img) {
            Storage::disk('public')->delete($img->path);
        }
        $equipment->delete();

        return $this->success(message: 'تم حذف الإعلان بنجاح.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  السوق العام (كل المقاولين المصرَّح لهم)
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/equipment/marketplace
    public function marketplace(Request $request)
    {
        $query = Equipment::visibleInMarketplace()
            ->whereHas('contractor', fn ($q) => $q->whereNull('equipment_banned_at'))
            ->with(['type:id,name_ar,icon', 'contractor:id,name,phone', 'primaryImage']);

        if ($request->filled('type_id')) {
            $query->where('equipment_type_id', $request->type_id);
        }
        if ($request->filled('governorate')) {
            $query->where('governorate', $request->governorate);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $paginator = $query->orderByDesc('is_featured')->latest()
            ->paginate($request->integer('per_page', 15))
            ->through(fn ($e) => $this->format($e));

        return $this->paginated($paginator);
    }

    // GET /api/v1/contractor/equipment/marketplace/{equipment} — شاشة تفاصيل الألية
    public function show(Equipment $equipment)
    {
        $equipment->load(['type:id,name_ar,icon', 'contractor:id,name,phone', 'images']);

        return $this->success($this->format($equipment));
    }

    // POST /api/v1/contractor/equipment/{equipment}/report
    public function report(Request $request, Equipment $equipment)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        EquipmentReport::create([
            'equipment_id'  => $equipment->id,
            'contractor_id' => $request->user()->id,
            'reason'        => $data['reason'],
            'status'        => 'pending',
        ]);

        return $this->success(message: 'تم إرسال بلاغك، وسيتم مراجعته من قِبل الإدارة.', code: 201);
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  الباقات والاشتراك (REQ-06)
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/equipment-packages
    public function packages()
    {
        return $this->success(EquipmentPackage::active()->orderBy('price')->get());
    }

    // GET /api/v1/contractor/equipment/subscription-status
    // شاشة "اشتراك الآليات السنوي" — حالة الاشتراك الحالي/المنتهي + تفاصيل الباقة والفاتورة
    public function subscriptionStatus(Request $request)
    {
        $contractor = $request->user();

        // آخر اشتراك بغض النظر عن انتهائه — عشان نقدر نعرض بطاقة "منتهي في ..." بتفاصيلها
        // كاملة، مش بس Active. activeEquipmentSubscription (تنتهي=false) يرجع null لو منتهي.
        $latest    = $contractor->equipmentSubscriptions()->with('package')->latest('expires_at')->first();
        $isExpired = $latest && $latest->expires_at->isPast();

        return $this->success([
            'has_free_access'   => $this->hasFreeTrialAccess(),
            'free_until'        => Setting::get('equipment_marketplace_free_until'),
            'has_subscription'  => (bool) $latest && ! $isExpired,
            'is_expired'        => $isExpired,
            'can_publish'       => $this->hasActiveMarketplaceAccess($contractor),
            'subscription'      => $latest ? [
                'id'                   => $latest->id,
                'equipment_package_id' => $latest->equipment_package_id,
                'package_name'         => $latest->package?->name,
                'price'                => $latest->package?->price,
                'currency'             => $latest->package?->currency ?? 'JOD',
                'duration_days'        => $latest->package?->duration_days,
                'starts_at'            => $latest->starts_at,
                'expires_at'           => $latest->expires_at,
                'status'               => $isExpired ? 'expired' : 'active',
            ] : null,
        ]);
    }

    private function hasFreeTrialAccess(): bool
    {
        $freeUntil = Setting::get('equipment_marketplace_free_until');
        if (! $freeUntil) {
            return true; // لم يُضبَط تاريخ انتهاء التجربة بعد = مجاني افتراضياً
        }

        return now()->lte(\Carbon\Carbon::parse($freeUntil));
    }

    private function hasActiveMarketplaceAccess($contractor): bool
    {
        return $contractor->hasActiveEquipmentMarketplaceAccess();
    }
}
