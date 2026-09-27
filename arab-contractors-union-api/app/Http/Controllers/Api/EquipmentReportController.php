<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\EquipmentReport;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

/** بلاغات الإبلاغ عن مشكلة بإعلانات سوق الآليات — إدارة الأدمن. */
class EquipmentReportController extends Controller
{
    use ApiResponseTrait;

    // GET /api/v1/dashboard/equipment-reports
    public function index(Request $request)
    {
        $query = EquipmentReport::with(['equipment:id,name,contractor_id', 'equipment.contractor:id,name', 'contractor:id,name,membership_number']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    // PATCH /api/v1/dashboard/equipment-reports/{equipmentReport}
    public function update(Request $request, EquipmentReport $equipmentReport)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,reviewed,dismissed',
        ]);

        $equipmentReport->update($data);

        AuditLogService::record($request->user(), 'equipment_report.status_changed', $equipmentReport, ['status' => $data['status']]);

        return $this->success($equipmentReport->fresh(), 'تم تحديث حالة البلاغ.');
    }
}
