<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\CountryDialCode;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * مقدمات الدول وأعلامها لخانات الجوال. "الحذف" من اللوحة = إخفاء (is_active=false) مثل المحافظات،
 * عشان الأرقام المحفوظة بمقدمة مخفية تضل تتقرأ صح.
 */
class CountryDialCodeController extends Controller
{
    use ApiResponseTrait;

    private function format(CountryDialCode $c): array
    {
        return [
            'id'          => $c->id,
            'name'        => $c->name,
            'iso_code'    => $c->iso_code,
            'dial_code'   => $c->dial_code,
            'flag_url'    => $c->flag_url,
            'has_custom_flag' => (bool) $c->flag_path,
            'min_digits'  => $c->min_digits,
            'max_digits'  => $c->max_digits,
            'starts_with' => $c->starts_with,
            'sort'        => $c->sort,
            'is_active'   => $c->is_active,
            'is_default'  => $c->is_default,
        ];
    }

    /** GET /api/v1/app/country-codes — المقدمات المفعّلة (عام: خانات الجوال باللوحة والتطبيق) */
    public function publicIndex()
    {
        $items = CountryDialCode::active()->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->get();

        return $this->success($items->map(fn ($c) => $this->format($c))->values());
    }

    /** GET /api/v1/dashboard/country-codes */
    public function index()
    {
        $items = CountryDialCode::orderBy('sort')->orderBy('id')->get();

        return $this->success($items->map(fn ($c) => $this->format($c))->values());
    }

    private function rules(?CountryDialCode $code = null): array
    {
        $req = $code ? 'sometimes|required' : 'required';

        return [
            'name'        => "$req|string|max:100",
            'iso_code'    => 'nullable|string|size:2|alpha',
            'dial_code'   => [$code ? 'sometimes' : 'required', 'regex:/^\+?[0-9]{1,4}$/', Rule::unique('country_dial_codes', 'dial_code')->ignore($code?->id)],
            'min_digits'  => "$req|integer|min:4|max:14",
            'max_digits'  => "$req|integer|min:4|max:14|gte:min_digits",
            'starts_with' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+(\s*,\s*[0-9]+)*$/'],
            'sort'        => 'nullable|integer|min:0',
            'is_active'   => 'sometimes|boolean',
            'is_default'  => 'sometimes|boolean',
            'flag'        => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:1024',
            'remove_flag' => 'sometimes|boolean',
        ];
    }

    private function messages(): array
    {
        return [
            'dial_code.regex'    => 'المقدمة أرقام فقط (1 إلى 4 أرقام، مثال 970).',
            'dial_code.unique'   => 'هذه المقدمة موجودة مسبقاً.',
            'max_digits.gte'     => 'أقصى عدد أرقام يجب أن يكون أكبر من أو يساوي الأدنى.',
            'starts_with.regex'  => 'أول رقم مسموح: أرقام مفصولة بفواصل (مثال 5 أو 3,5,6).',
            'iso_code.size'      => 'رمز الدولة حرفان إنجليزيان (مثال PS).',
        ];
    }

    private function persist(Request $request, CountryDialCode $code): CountryDialCode
    {
        $data = $request->validate($this->rules($code->exists ? $code : null), $this->messages(), [
            'name' => 'اسم الدولة', 'min_digits' => 'أدنى عدد أرقام', 'max_digits' => 'أقصى عدد أرقام',
        ]);

        if (isset($data['dial_code'])) {
            $data['dial_code'] = ltrim($data['dial_code'], '+');
        }
        if (array_key_exists('iso_code', $data)) {
            $data['iso_code'] = $data['iso_code'] ? strtoupper($data['iso_code']) : null;
        }
        if (array_key_exists('starts_with', $data)) {
            $data['starts_with'] = $data['starts_with'] ? preg_replace('/\s+/', '', $data['starts_with']) : null;
        }

        if ($request->hasFile('flag') || $request->boolean('remove_flag')) {
            if ($code->flag_path) {
                Storage::disk('public')->delete($code->flag_path);
            }
            $data['flag_path'] = $request->hasFile('flag') ? $request->file('flag')->store('country-flags', 'public') : null;
        }
        unset($data['flag'], $data['remove_flag']);

        DB::transaction(function () use ($code, $data) {
            $code->fill($data)->save();
            // مقدمة افتراضية وحدة بس (هي اللي بتنختار تلقائياً وبتنفحص عليها الأرقام المحلية)
            if ($code->is_default) {
                CountryDialCode::where('id', '!=', $code->id)->update(['is_default' => false]);
                if (! $code->is_active) {
                    $code->update(['is_active' => true]);
                }
            }
        });

        return $code->fresh();
    }

    /** POST /api/v1/dashboard/country-codes (multipart) */
    public function store(Request $request)
    {
        $code = $this->persist($request, new CountryDialCode());
        AuditLogService::record(Auth::user(), 'country_dial_code.created', $code, ['dial_code' => $code->dial_code]);

        return $this->success($this->format($code), 'تم إضافة المقدمة بنجاح.', 201);
    }

    /** POST /api/v1/dashboard/country-codes/{countryDialCode} (multipart، _method=PATCH) */
    public function update(Request $request, CountryDialCode $countryDialCode)
    {
        $code = $this->persist($request, $countryDialCode);
        AuditLogService::record(Auth::user(), 'country_dial_code.updated', $code, ['dial_code' => $code->dial_code]);

        return $this->success($this->format($code), 'تم تحديث المقدمة بنجاح.');
    }

    /** DELETE — إخفاء فقط */
    public function destroy(CountryDialCode $countryDialCode)
    {
        if ($countryDialCode->is_default) {
            return $this->error('لا يمكن إخفاء المقدمة الافتراضية. اختر مقدمة افتراضية أخرى أولاً.', 422);
        }

        $countryDialCode->update(['is_active' => false]);
        AuditLogService::record(Auth::user(), 'country_dial_code.deactivated', $countryDialCode);

        return $this->success(message: 'تم إخفاء المقدمة (لن تظهر ضمن الخيارات).');
    }
}
