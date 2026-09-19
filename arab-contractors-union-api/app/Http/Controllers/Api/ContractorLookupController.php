<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ContractorField;
use App\Models\ContractorGrade;
use App\Models\ContractorSpecialization;
use App\Services\AuditLogService;
use App\Support\ContractorLookups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * CRUD لوحة الأدمن لجداول المرجعية (مجالات/اختصاصات/درجات) — REQ-01 #7.
 *
 * هذه الأكواد (code) مستهلكة مباشرة بمحرك حساب الرسوم (MembershipFeeCalculator) وبتوليد
 * الشهادات (PDF/Docx) وبعمود contractors.specialties (JSON) القائم — لذلك:
 *   - لا يوجد حذف فعلي (hard delete): "حذف" من اللوحة = is_active=false فقط، فتختفي من
 *     خيارات فورم إضافة/تعديل مقاول جديد بينما تبقى قابلة للعرض في السجلات القديمة.
 *   - لا يمكن تعديل قيمة code بعد الإنشاء (غير موجودة أصلاً بقواعد update) تفادياً لتعارضها
 *     مع بيانات specialties[] المخزَّنة مسبقاً بنفس الكود.
 *   - كل عملية إنشاء/تعديل/تعطيل تُفرِغ كاش ContractorLookups فوراً.
 */
class ContractorLookupController extends Controller
{
    use ApiResponseTrait;

    // ─── المجالات ────────────────────────────────────────────────────────────

    public function fieldsIndex()
    {
        $rows = ContractorField::orderBy('sort_order')->orderBy('id')->get();

        return $this->success($rows, 'تم جلب المجالات بنجاح');
    }

    public function fieldsStore(Request $request)
    {
        $data = $request->validate([
            'code'       => 'required|integer|min:1|unique:contractor_fields,code',
            'name'       => 'required|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $field = ContractorField::create($data);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_field.created', $field);

        return $this->success($field, 'تم إضافة المجال بنجاح', 201);
    }

    public function fieldsUpdate(Request $request, ContractorField $contractorField)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active'  => 'sometimes|boolean',
        ]);

        $contractorField->update($data);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_field.updated', $contractorField, $data);

        return $this->success($contractorField->fresh(), 'تم تحديث المجال بنجاح');
    }

    /** "حذف" = تعطيل فقط (is_active=false) — راجع تعليق أعلى الكلاس. */
    public function fieldsDestroy(ContractorField $contractorField)
    {
        $contractorField->update(['is_active' => false]);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_field.deactivated', $contractorField);

        return $this->success(message: 'تم إخفاء المجال بنجاح (لن يظهر ضمن خيارات المقاولين الجدد).');
    }

    // ─── الاختصاصات ──────────────────────────────────────────────────────────

    public function specializationsIndex()
    {
        $rows = ContractorSpecialization::with('field:id,code,name')
            ->orderBy('sort_order')->orderBy('id')->get();

        return $this->success($rows, 'تم جلب الاختصاصات بنجاح');
    }

    public function specializationsStore(Request $request)
    {
        $data = $request->validate([
            'code'                 => 'required|integer|min:1|unique:contractor_specializations,code',
            'contractor_field_id'  => 'nullable|integer|exists:contractor_fields,id',
            'name'                 => 'required|string|max:100',
            'sort_order'           => 'nullable|integer|min:0',
        ]);

        $specialization = ContractorSpecialization::create($data);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_specialization.created', $specialization);

        return $this->success($specialization->load('field:id,code,name'), 'تم إضافة الاختصاص بنجاح', 201);
    }

    public function specializationsUpdate(Request $request, ContractorSpecialization $contractorSpecialization)
    {
        $data = $request->validate([
            'contractor_field_id' => 'sometimes|nullable|integer|exists:contractor_fields,id',
            'name'                => 'sometimes|string|max:100',
            'sort_order'          => 'sometimes|integer|min:0',
            'is_active'           => 'sometimes|boolean',
        ]);

        $contractorSpecialization->update($data);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_specialization.updated', $contractorSpecialization, $data);

        return $this->success($contractorSpecialization->fresh()->load('field:id,code,name'), 'تم تحديث الاختصاص بنجاح');
    }

    public function specializationsDestroy(ContractorSpecialization $contractorSpecialization)
    {
        $contractorSpecialization->update(['is_active' => false]);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_specialization.deactivated', $contractorSpecialization);

        return $this->success(message: 'تم إخفاء الاختصاص بنجاح (لن يظهر ضمن خيارات المقاولين الجدد).');
    }

    // ─── الدرجات ──────────────────────────────────────────────────────────────

    public function gradesIndex()
    {
        $rows = ContractorGrade::orderBy('sort_order')->orderBy('id')->get();

        return $this->success($rows, 'تم جلب الدرجات بنجاح');
    }

    public function gradesStore(Request $request)
    {
        $data = $request->validate([
            'code'                  => 'required|string|max:20|unique:contractor_grades,code',
            'label'                 => 'required|string|max:100',
            // level لازم يكون فريداً — MembershipFeeCalculator يعتمد عليه كترتيب صارم لاختيار
            // "أعلى مجال" (أقل grade_level)؛ تكرار نفس القيمة بين درجتين يجعل اختيار المجال
            // صاحب النسبة 100% غير محدَّد (يعتمد على ترتيب الاستعلام لا على منطق واضح).
            'level'                 => ['required', 'integer', 'min:1', Rule::unique('contractor_grades', 'level')],
            'eligible_field_codes'  => 'nullable|array',
            'eligible_field_codes.*' => 'integer',
            'sort_order'            => 'nullable|integer|min:0',
        ]);

        $grade = ContractorGrade::create($data);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_grade.created', $grade);

        return $this->success($grade, 'تم إضافة الدرجة بنجاح', 201);
    }

    public function gradesUpdate(Request $request, ContractorGrade $contractorGrade)
    {
        $data = $request->validate([
            'label'                  => 'sometimes|string|max:100',
            'level'                  => ['sometimes', 'integer', 'min:1', Rule::unique('contractor_grades', 'level')->ignore($contractorGrade->id)],
            'eligible_field_codes'   => 'sometimes|nullable|array',
            'eligible_field_codes.*' => 'integer',
            'sort_order'             => 'sometimes|integer|min:0',
            'is_active'              => 'sometimes|boolean',
        ]);

        $contractorGrade->update($data);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_grade.updated', $contractorGrade, $data);

        return $this->success($contractorGrade->fresh(), 'تم تحديث الدرجة بنجاح');
    }

    public function gradesDestroy(ContractorGrade $contractorGrade)
    {
        $contractorGrade->update(['is_active' => false]);
        ContractorLookups::clearCache();
        AuditLogService::record(Auth::user(), 'contractor_grade.deactivated', $contractorGrade);

        return $this->success(message: 'تم إخفاء الدرجة بنجاح (لن تظهر ضمن خيارات المقاولين الجدد).');
    }
}
