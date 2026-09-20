<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Equipment;
use App\Models\EquipmentImage;
use App\Models\EquipmentPackage;
use App\Models\EquipmentReport;
use App\Models\EquipmentReservation;
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

    public function __construct(
        private \App\Services\ContractorFinancialService $financialService
    ) {}

    private function format(Equipment $e): array
    {
        return [
            'id'                => $e->id,
            'contractor_id'     => $e->contractor_id,
            // بطاقة "المالك/المقاول" بشاشة تفاصيل الألية — زر تواصل واتساب مباشر (owner_phone)
            'contractor_name'   => $e->contractor?->name,
            'equipment_type_id' => $e->equipment_type_id,
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

        if ($this->financialService->outstandingDuesTotal($contractor) > 0) {
            return $this->error('لا يمكن إضافة آلية جديدة قبل تسوية الذمم المالية المستحقّة.', 403, null, 'dues_pending');
        }

        $data = $request->validate([
            'equipment_type_id'  => 'required|exists:equipment_types,id',
            'name'               => 'required|string|max:255',
            'brand'              => 'nullable|string|max:100',
            'description'        => 'nullable|string|max:2000',
            'manufacture_year'   => 'nullable|integer|min:1970|max:' . date('Y'),
            'power'              => 'nullable|string|max:50',
            'condition'          => 'nullable|in:excellent,good,fair',
            'contract_type'      => 'nullable|in:daily,weekly,monthly',
            'governorate'        => 'nullable|string|max:100',
            'city'               => 'nullable|string|max:100',
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
            // كان غائباً عن قواعد التحقق هون رغم وجوده بالفورم المفترَض — أي تعديل لنوع
            // الآلية من التطبيق كان يُرسَل ويُتجاهَل بصمت لأن Laravel يستبعد أي حقل غير
            // مُعرَّف بقواعد validate() من مصفوفة النتيجة (REQ-08 #9)
            'equipment_type_id' => 'sometimes|exists:equipment_types,id',
            'name'              => 'sometimes|string|max:255',
            'brand'             => 'nullable|string|max:100',
            'description'       => 'nullable|string|max:2000',
            'manufacture_year'  => 'nullable|integer|min:1970|max:' . date('Y'),
            'power'             => 'nullable|string|max:50',
            'condition'         => 'nullable|in:excellent,good,fair',
            'contract_type'     => 'sometimes|in:daily,weekly,monthly',
            'governorate'       => 'nullable|string|max:100',
            'city'              => 'nullable|string|max:100',
            'owner_phone'       => 'nullable|string|max:20',
            'is_hidden'         => 'sometimes|boolean',
            'needs_maintenance' => 'sometimes|boolean',
        ]);

        $equipment->update($data);

        return $this->success($this->format($equipment->fresh(['type', 'images'])), 'تم تحديث الإعلان بنجاح.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  صور الإعلان بعد النشر (REQ-08 #2/#3/#5) — لم تكن موجودة أصلاً: الصور كانت
    //  تُرفَع فقط ضمن نفس طلب store() عند الإنشاء، وما في طريقة لإضافة/حذف صورة
    //  لاحقاً من التطبيق — هذا هو الفجوة الحقيقية خلف شكاوى "ما في زر حفظ بعد رفع
    //  الصور" و"الرفع لازم يصير مع الإضافة" بالشيت.
    // ═════════════════════════════════════════════════════════════════════════

    // POST /api/v1/contractor/equipment/{equipment}/images
    public function uploadImages(Request $request, Equipment $equipment)
    {
        if ($equipment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بتعديل هذا الإعلان.', 403);
        }

        $request->validate([
            'images'   => 'required|array|min:1',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        // الحد 8 صور إجمالي (موجودة + جديدة)، لا الدفعة المرفوعة فقط
        $existingCount = $equipment->images()->count();
        $newCount      = count($request->file('images'));

        if ($existingCount + $newCount > 8) {
            return $this->error(
                "الحد الأقصى 8 صور لكل آلية. لديك حالياً {$existingCount} صورة، ولا يمكن إضافة {$newCount} أخرى.",
                422,
            );
        }

        $lastOrder  = $equipment->images()->max('sort_order') ?? -1;
        $hasPrimary = $equipment->images()->where('is_primary', true)->exists();

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store('equipment/' . $equipment->id, 'public');
            $lastOrder++;
            EquipmentImage::create([
                'equipment_id' => $equipment->id,
                'path'         => $path,
                'is_primary'   => ! $hasPrimary && $index === 0,
                'sort_order'   => $lastOrder,
            ]);
            $hasPrimary = true;
        }

        return $this->success($this->format($equipment->fresh(['type', 'images'])), 'تمت إضافة الصور بنجاح.', 201);
    }

    // DELETE /api/v1/contractor/equipment/{equipment}/images/{equipmentImage}
    public function deleteImage(Request $request, Equipment $equipment, EquipmentImage $equipmentImage)
    {
        if ($equipment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بتعديل هذا الإعلان.', 403);
        }

        if ($equipmentImage->equipment_id !== $equipment->id) {
            return $this->error('الصورة لا تعود لهذا الإعلان.', 422);
        }

        Storage::disk('public')->delete($equipmentImage->path);
        $wasPrimary = $equipmentImage->is_primary;
        $equipmentImage->delete();

        if ($wasPrimary) {
            $next = $equipment->images()->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }

        return $this->success($this->format($equipment->fresh(['type', 'images'])), 'تم حذف الصورة بنجاح.');
    }

    // POST /api/v1/contractor/equipment/{equipment}/images/{equipmentImage}/primary
    public function setPrimaryImage(Request $request, Equipment $equipment, EquipmentImage $equipmentImage)
    {
        if ($equipment->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بتعديل هذا الإعلان.', 403);
        }

        if ($equipmentImage->equipment_id !== $equipment->id) {
            return $this->error('الصورة لا تعود لهذا الإعلان.', 422);
        }

        $equipment->images()->update(['is_primary' => false]);
        $equipmentImage->update(['is_primary' => true]);

        return $this->success($this->format($equipment->fresh(['type', 'images'])), 'تم تعيين الصورة الرئيسية.');
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
    //  حجوزات فعلية (REQ-08 #6) — المستأجر يطلب فترة، تُقبل أوتوماتيكياً إن كانت
    //  متاحة (بدون خطوة موافقة من المالك/الإدارة). يستبدل الاعتماد الوحيد على
    //  EquipmentBlockedDate (أداة حجب يدوية بلا هوية مستأجر) لتمثيل "المحجوز فعلاً".
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/equipment/marketplace/{equipment}/availability
    // نطاقات التواريخ غير المتاحة (حجوزات مؤكَّدة + حجب إداري) — لعرضها بتقويم الطلب
    public function availability(Equipment $equipment)
    {
        $reservations = $equipment->reservations()
            ->where('status', 'confirmed')
            ->where('end_date', '>=', now()->toDateString())
            ->get(['start_date', 'end_date']);

        $blocked = $equipment->blockedDates()
            ->where('blocked_date', '>=', now()->toDateString())
            ->get(['blocked_date', 'reason']);

        return $this->success([
            'reserved_ranges' => $reservations->map(fn ($r) => [
                'start_date' => $r->start_date->toDateString(),
                'end_date'   => $r->end_date->toDateString(),
            ])->values(),
            'blocked_dates' => $blocked->map(fn ($b) => [
                'date'   => $b->blocked_date->toDateString(),
                'reason' => $b->reason,
            ])->values(),
        ]);
    }

    // POST /api/v1/contractor/equipment/{equipment}/reservations
    public function requestReservation(Request $request, Equipment $equipment)
    {
        $contractor = $request->user();

        if ($equipment->contractor_id === $contractor->id) {
            return $this->error('لا يمكنك حجز آليتك الخاصة.', 422);
        }

        if ($equipment->is_hidden || $equipment->status !== 'visible' || $equipment->needs_maintenance) {
            return $this->error('هذه الآلية غير متاحة للحجز حالياً.', 422);
        }

        $data = $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'notes'      => 'nullable|string|max:500',
        ]);

        if (EquipmentReservation::hasOverlap($equipment->id, $data['start_date'], $data['end_date'])) {
            return $this->error(
                'الفترة المطلوبة غير متاحة — هناك حجز أو حجب سابق يتقاطع معها. جرّب فترة أخرى.',
                422,
                null,
                'dates_unavailable',
            );
        }

        $reservation = EquipmentReservation::create([
            'equipment_id'  => $equipment->id,
            'contractor_id' => $contractor->id,
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'status'        => 'confirmed',
            'notes'         => $data['notes'] ?? null,
        ]);

        return $this->success(
            $reservation->fresh(['equipment:id,name'])->toArray(),
            'تم تأكيد الحجز بنجاح.',
            201,
        );
    }

    // GET /api/v1/contractor/my-equipment-reservations — حجوزاتي كمستأجر
    public function myReservations(Request $request)
    {
        $paginator = $request->user()->equipmentReservations()
            ->with('equipment:id,name,contractor_id')
            ->latest('start_date')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($paginator);
    }

    // DELETE /api/v1/contractor/equipment-reservations/{equipmentReservation}
    public function cancelReservation(Request $request, EquipmentReservation $equipmentReservation)
    {
        if ($equipmentReservation->contractor_id !== $request->user()->id) {
            return $this->error('غير مصرَّح لك بإلغاء هذا الحجز.', 403);
        }

        if ($equipmentReservation->status !== 'confirmed') {
            return $this->error('هذا الحجز ملغى مسبقاً.', 422);
        }

        if ($equipmentReservation->start_date->isPast()) {
            return $this->error('لا يمكن إلغاء حجز بدأت فترته بالفعل.', 422);
        }

        $equipmentReservation->update(['status' => 'cancelled']);

        return $this->success(message: 'تم إلغاء الحجز.');
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
