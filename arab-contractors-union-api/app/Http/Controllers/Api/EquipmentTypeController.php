<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EquipmentType;
use Illuminate\Http\Request;

class EquipmentTypeController extends Controller
{
    // GET /api/equipment-types
    public function index()
    {
        return response()->json(EquipmentType::orderBy('name_ar')->get());
    }

    // POST /api/equipment-types
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_ar'   => 'required|string|max:100',
            'name_en'   => 'nullable|string|max:100',
            'icon'      => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $type = EquipmentType::create($validated);

        return response()->json($type, 201);
    }

    // PATCH /api/equipment-types/{type}
    public function update(Request $request, EquipmentType $equipmentType)
    {
        $validated = $request->validate([
            'name_ar'   => 'sometimes|string|max:100',
            'name_en'   => 'nullable|string|max:100',
            'icon'      => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        $equipmentType->update($validated);

        return response()->json($equipmentType);
    }

    // DELETE /api/equipment-types/{type}
    public function destroy(EquipmentType $equipmentType)
    {
        if ($equipmentType->equipment()->count() > 0) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا النوع لأنه مرتبط بآليات موجودة.',
            ], 422);
        }

        $equipmentType->delete();

        return response()->json(['message' => 'تم حذف النوع بنجاح']);
    }
}
