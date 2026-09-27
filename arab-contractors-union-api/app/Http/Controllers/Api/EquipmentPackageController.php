<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\EquipmentPackage;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

/** باقات اشتراك سوق الآليات — CRUD إداري (REQ-06). */
class EquipmentPackageController extends Controller
{
    use ApiResponseTrait;

    // GET /api/v1/dashboard/equipment-packages
    public function index()
    {
        return $this->success(EquipmentPackage::orderBy('price')->get());
    }

    // POST /api/v1/dashboard/equipment-packages
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'price'         => 'required|numeric|min:0',
            'currency'      => 'nullable|string|max:3',
            'duration_days' => 'required|integer|min:1',
            'ads_limit'     => 'nullable|integer|min:1',
            'is_active'     => 'boolean',
        ]);

        $package = EquipmentPackage::create($data);

        AuditLogService::record($request->user(), 'equipment_package.created', $package, ['name' => $package->name]);

        return $this->success($package, 'تمت إضافة الباقة بنجاح.', 201);
    }

    // PATCH /api/v1/dashboard/equipment-packages/{equipmentPackage}
    public function update(Request $request, EquipmentPackage $equipmentPackage)
    {
        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'price'         => 'sometimes|numeric|min:0',
            'currency'      => 'nullable|string|max:3',
            'duration_days' => 'sometimes|integer|min:1',
            'ads_limit'     => 'nullable|integer|min:1',
            'is_active'     => 'boolean',
        ]);

        $equipmentPackage->update($data);

        AuditLogService::record($request->user(), 'equipment_package.updated', $equipmentPackage, $data);

        return $this->success($equipmentPackage->fresh(), 'تم تحديث الباقة.');
    }

    // DELETE /api/v1/dashboard/equipment-packages/{equipmentPackage}
    public function destroy(Request $request, EquipmentPackage $equipmentPackage)
    {
        AuditLogService::record($request->user(), 'equipment_package.deleted', $equipmentPackage, ['name' => $equipmentPackage->name]);

        $equipmentPackage->delete();

        return $this->success(message: 'تم حذف الباقة.');
    }
}
