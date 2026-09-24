<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\City;
use App\Models\Governorate;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * CRUD لوحة الأدمن للمحافظات والمدن (بيانات مرجعية يستخدمها فورم بيانات المقاول
 * عبر contractors.governorate_id / city_id، وGET /api/v1/app/governorates العام).
 *
 * نفس نمط ContractorLookupController: "حذف" من اللوحة = is_active=false فقط، أبداً hard
 * delete — الأعمدة الأصلية governorate_id/city_id على contractors هي foreign keys
 * (nullOnDelete)، فحذف صف فعلي يفرغ الحقل عند كل مقاول مرتبط به.
 */
class GovernorateController extends Controller
{
    use ApiResponseTrait;

    // ─── المحافظات ───────────────────────────────────────────────────────────

    public function governoratesIndex()
    {
        $rows = Governorate::withCount('cities')->orderBy('sort')->orderBy('id')->get();

        return $this->success($rows, 'تم جلب المحافظات بنجاح');
    }

    public function governoratesStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:governorates,name',
            'sort' => 'nullable|integer|min:0',
        ]);

        $governorate = Governorate::create($data);
        AuditLogService::record(Auth::user(), 'governorate.created', $governorate);

        return $this->success($governorate, 'تم إضافة المحافظة بنجاح', 201);
    }

    public function governoratesUpdate(Request $request, Governorate $governorate)
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:100', Rule::unique('governorates', 'name')->ignore($governorate->id)],
            'sort'      => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $governorate->update($data);
        AuditLogService::record(Auth::user(), 'governorate.updated', $governorate, $data);

        return $this->success($governorate->fresh(), 'تم تحديث المحافظة بنجاح');
    }

    /** "حذف" = تعطيل فقط (is_active=false) — راجع تعليق أعلى الكلاس. */
    public function governoratesDestroy(Governorate $governorate)
    {
        $governorate->update(['is_active' => false]);
        AuditLogService::record(Auth::user(), 'governorate.deactivated', $governorate);

        return $this->success(message: 'تم إخفاء المحافظة بنجاح (لن تظهر ضمن خيارات المقاولين الجدد).');
    }

    // ─── المدن ───────────────────────────────────────────────────────────────

    public function citiesIndex()
    {
        $rows = City::with('governorate:id,name')->orderBy('governorate_id')->orderBy('sort')->orderBy('id')->get();

        return $this->success($rows, 'تم جلب المدن بنجاح');
    }

    public function citiesStore(Request $request)
    {
        $data = $request->validate([
            'governorate_id' => 'required|integer|exists:governorates,id',
            'name'           => 'required|string|max:100',
            'sort'           => 'nullable|integer|min:0',
        ]);

        $city = City::create($data);
        AuditLogService::record(Auth::user(), 'city.created', $city);

        return $this->success($city->load('governorate:id,name'), 'تم إضافة المدينة بنجاح', 201);
    }

    public function citiesUpdate(Request $request, City $city)
    {
        $data = $request->validate([
            'governorate_id' => 'sometimes|integer|exists:governorates,id',
            'name'           => 'sometimes|string|max:100',
            'sort'           => 'sometimes|integer|min:0',
            'is_active'      => 'sometimes|boolean',
        ]);

        $city->update($data);
        AuditLogService::record(Auth::user(), 'city.updated', $city, $data);

        return $this->success($city->fresh()->load('governorate:id,name'), 'تم تحديث المدينة بنجاح');
    }

    public function citiesDestroy(City $city)
    {
        $city->update(['is_active' => false]);
        AuditLogService::record(Auth::user(), 'city.deactivated', $city);

        return $this->success(message: 'تم إخفاء المدينة بنجاح (لن تظهر ضمن خيارات المقاولين الجدد).');
    }
}
