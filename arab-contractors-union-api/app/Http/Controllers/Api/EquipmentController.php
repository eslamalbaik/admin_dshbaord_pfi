<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentBlockedDate;
use App\Models\EquipmentImage;
use App\Models\EquipmentReservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EquipmentController extends Controller
{
    // GET /api/equipment
    public function index(Request $request)
    {
        $query = Equipment::with(['type:id,name_ar,icon', 'contractor:id,name,phone', 'primaryImage']);

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhereHas('contractor', fn($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('type_id')) {
            $query->where('equipment_type_id', $request->type_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('governorate')) {
            $query->where('governorate', $request->governorate);
        }

        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        if ($request->filled('contractor_id')) {
            $query->where('contractor_id', $request->contractor_id);
        }

        $perPage = (int) $request->get('per_page', 15);

        return response()->json($query->latest()->paginate($perPage));
    }

    // GET /api/equipment/stats
    public function stats()
    {
        return response()->json([
            'total'     => Equipment::count(),
            'visible'   => Equipment::where('status', 'visible')->count(),
            'hidden'    => Equipment::where('status', 'hidden')->count(),
            'suspended' => Equipment::where('status', 'suspended')->count(),
        ]);
    }

    // POST /api/equipment
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contractor_id'     => 'required|exists:contractors,id',
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'name'              => 'required|string|max:255',
            'brand'             => 'nullable|string|max:100',
            'description'       => 'nullable|string|max:2000',
            'manufacture_year'  => 'nullable|integer|min:1970|max:' . date('Y'),
            'power'             => 'nullable|string|max:50',
            'condition'         => 'nullable|in:excellent,good,needs_maintenance',
            'contract_type'     => 'nullable|in:daily,weekly,monthly',
            'governorate'       => 'nullable|string|max:100',
            'city'              => 'nullable|string|max:100',
            'owner_phone'       => 'nullable|string|max:20',
            'status'            => 'nullable|in:visible,hidden,suspended',
            'is_featured'       => 'nullable|boolean',
            'needs_maintenance' => 'nullable|boolean',
            'admin_notes'       => 'nullable|string',
            'images'            => 'nullable|array|max:8',
            'images.*'          => 'image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        $images = $request->file('images', []);
        unset($validated['images']);

        $equipment = Equipment::create($validated);

        foreach ($images as $index => $file) {
            $path = $file->store('equipment/' . $equipment->id, 'public');
            EquipmentImage::create([
                'equipment_id' => $equipment->id,
                'path'         => $path,
                'is_primary'   => $index === 0,
                'sort_order'   => $index,
            ]);
        }

        return response()->json($equipment->load(['type', 'contractor', 'images']), 201);
    }

    // GET /api/equipment/{equipment}
    public function show(Equipment $equipment)
    {
        $equipment->load(['type', 'contractor', 'images', 'blockedDates']);

        return response()->json($equipment);
    }

    // PATCH /api/equipment/{equipment}
    public function update(Request $request, Equipment $equipment)
    {
        $validated = $request->validate([
            'contractor_id'     => 'sometimes|exists:contractors,id',
            'equipment_type_id' => 'sometimes|exists:equipment_types,id',
            'name'              => 'sometimes|string|max:255',
            'brand'             => 'nullable|string|max:100',
            'description'       => 'nullable|string|max:2000',
            'manufacture_year'  => 'nullable|integer|min:1970|max:' . date('Y'),
            'power'             => 'nullable|string|max:50',
            'condition'         => 'nullable|in:excellent,good,needs_maintenance',
            'contract_type'     => 'nullable|in:daily,weekly,monthly',
            'governorate'       => 'nullable|string|max:100',
            'city'              => 'nullable|string|max:100',
            'owner_phone'       => 'nullable|string|max:20',
            'status'            => 'nullable|in:visible,hidden,suspended',
            'is_featured'       => 'nullable|boolean',
            'needs_maintenance' => 'nullable|boolean',
            'admin_notes'       => 'nullable|string',
        ]);

        $equipment->update($validated);

        return response()->json($equipment->load(['type', 'contractor', 'images']));
    }

    // DELETE /api/equipment/{equipment}
    public function destroy(Equipment $equipment)
    {
        // delete stored images from disk
        foreach ($equipment->images as $img) {
            Storage::disk('public')->delete($img->path);
        }

        $equipment->delete();

        return response()->json(['message' => 'تم حذف الآلية بنجاح']);
    }

    // POST /api/equipment/{equipment}/images
    public function uploadImages(Request $request, Equipment $equipment)
    {
        $request->validate([
            'images'   => 'required|array|min:1',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        // الحد 8 صور إجمالي (موجودة + جديدة)، لا الدفعة المرفوعة فقط — كانت الدفعة تُفحص لوحدها
        // مما سمح بتجاوز الحد الفعلي عبر رفعات متتالية
        $existingCount = $equipment->images()->count();
        $newCount      = count($request->file('images'));

        if ($existingCount + $newCount > 8) {
            return response()->json([
                'message' => "الحد الأقصى 8 صور لكل آلية. لديها حالياً {$existingCount} صورة، ولا يمكن إضافة {$newCount} أخرى.",
            ], 422);
        }

        $lastOrder = $equipment->images()->max('sort_order') ?? -1;
        $hasPrimary = $equipment->images()->where('is_primary', true)->exists();
        $uploaded = [];

        foreach ($request->file('images') as $index => $file) {
            $path = $file->store('equipment/' . $equipment->id, 'public');
            $lastOrder++;
            $img = EquipmentImage::create([
                'equipment_id' => $equipment->id,
                'path'         => $path,
                'is_primary'   => !$hasPrimary && $index === 0,
                'sort_order'   => $lastOrder,
            ]);
            $hasPrimary = true;
            $uploaded[] = $img;
        }

        return response()->json($uploaded, 201);
    }

    // DELETE /api/equipment/{equipment}/images/{image}
    public function deleteImage(Equipment $equipment, EquipmentImage $equipmentImage)
    {
        if ($equipmentImage->equipment_id !== $equipment->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        Storage::disk('public')->delete($equipmentImage->path);
        $wasPrimary = $equipmentImage->is_primary;
        $equipmentImage->delete();

        // if deleted image was primary → promote next image
        if ($wasPrimary) {
            $next = $equipment->images()->orderBy('sort_order')->first();
            if ($next) {
                $next->update(['is_primary' => true]);
            }
        }

        return response()->json(['message' => 'تم حذف الصورة']);
    }

    // POST /api/equipment/{equipment}/images/{image}/primary
    public function setPrimaryImage(Equipment $equipment, EquipmentImage $equipmentImage)
    {
        if ($equipmentImage->equipment_id !== $equipment->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $equipment->images()->update(['is_primary' => false]);
        $equipmentImage->update(['is_primary' => true]);

        return response()->json(['message' => 'تم تعيين الصورة الرئيسية']);
    }

    // GET /api/equipment/{equipment}/blocked-dates
    public function blockedDates(Equipment $equipment)
    {
        $dates = $equipment->blockedDates()->orderBy('blocked_date')->get();

        return response()->json($dates);
    }

    // GET /api/equipment/{equipment}/reservations — حجوزات فعلية طلبها مقاولون عبر التطبيق
    // (REQ-08 #6)، للعرض فقط بجانب أداة الحجب اليدوي (blocked-dates) أعلاه
    public function reservations(Equipment $equipment)
    {
        $reservations = $equipment->reservations()
            ->with('contractor:id,name,phone')
            ->orderByDesc('start_date')
            ->get();

        return response()->json($reservations);
    }

    // POST /api/equipment/{equipment}/blocked-dates
    public function addBlockedDate(Request $request, Equipment $equipment)
    {
        $request->validate([
            'dates'    => 'required|array|min:1',
            'dates.*'  => 'date',
            'reason'   => 'nullable|in:booked,maintenance,other',
        ]);

        $reason = $request->reason ?? 'booked';
        $inserted = [];

        foreach (array_unique($request->dates) as $date) {
            $record = EquipmentBlockedDate::firstOrCreate(
                ['equipment_id' => $equipment->id, 'blocked_date' => $date],
                ['reason' => $reason]
            );
            $inserted[] = $record;
        }

        return response()->json($inserted, 201);
    }

    // DELETE /api/equipment/{equipment}/blocked-dates/{blockedDate}
    public function removeBlockedDate(Equipment $equipment, EquipmentBlockedDate $blockedDate)
    {
        if ($blockedDate->equipment_id !== $equipment->id) {
            return response()->json(['message' => 'غير مصرح'], 403);
        }

        $blockedDate->delete();

        return response()->json(['message' => 'تم إلغاء التاريخ']);
    }
}
