<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\GradeFee;
use Illuminate\Http\Request;

class GradeFeeController extends Controller
{
    use ApiResponseTrait;

    /** تنسيق صف رسوم درجة لاستجابة الـ API */
    private function format(GradeFee $g): array
    {
        return [
            'id'                   => $g->id,
            'grade_code'           => $g->grade_code,
            'grade_label'          => $g->grade_label,
            'sort_order'           => $g->sort_order,
            'registration_fee_jod' => $g->registration_fee_jod,
            'annual_fee_jod'       => $g->annual_fee_jod,
        ];
    }

    /** GET /api/v1/dashboard/grade-fees — جدول رسوم الدرجات الستة (المادة 37) */
    public function index()
    {
        $rows = GradeFee::ordered()->get()->map(fn ($g) => $this->format($g))->values();

        return $this->success($rows, 'تم جلب جدول رسوم الدرجات بنجاح');
    }

    /**
     * PUT /api/v1/dashboard/grade-fees/{gradeFee}
     * تعديل مبلغ رسم درجة — صلاحية مجلس الإدارة (المادة 37/ت)، لا يوجد إنشاء/حذف: الصفوف الستة ثابتة.
     */
    public function update(Request $request, GradeFee $gradeFee)
    {
        $data = $request->validate([
            'grade_label'          => 'sometimes|string|max:50',
            'registration_fee_jod' => 'sometimes|numeric|min:0|max:99999',
            'annual_fee_jod'       => 'sometimes|numeric|min:0|max:99999',
            'reason'               => 'nullable|string|max:500',
        ]);

        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        $before = array_intersect_key($gradeFee->only(array_keys($data)), $data);

        $gradeFee->update($data);

        // سجل المحددات الهامة — تعديل رسوم العضوية
        \App\Services\AuditLogService::recordCritical(
            \Illuminate\Support\Facades\Auth::user(),
            'grade_fee.updated',
            $gradeFee,
            before: $before,
            after: $data,
            reason: $reason,
            context: ['grade_code' => $gradeFee->grade_code, 'grade_label' => $gradeFee->grade_label],
        );

        return $this->success($this->format($gradeFee->fresh()), 'تم تحديث رسوم الدرجة بنجاح');
    }
}
