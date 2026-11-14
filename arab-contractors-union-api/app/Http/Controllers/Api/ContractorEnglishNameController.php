<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Services\AuditLogService;
use App\Support\EnglishNameSuggester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * اسم الشركة وعنوانها بالإنجليزي (لشهادة العضوية الإنجليزية): اقتراح تلقائي من العربي
 * (EnglishNameSuggester)، وصفحة مراجعة جماعية بتعبّي الناقص لكل المقاولين. ما في شي
 * بينحفظ تلقائياً — الأدمن بيراجع الاقتراح وبيحفظ.
 */
class ContractorEnglishNameController extends Controller
{
    use ApiResponseTrait;

    private const NAME_RULE    = ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9 .,&\'()\/\-]+$/'];
    private const ADDRESS_RULE = ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9 .,&\'()\/\-#]+$/'];

    /** POST dashboard/contractors/english-suggest — لفورم المقاول (الإنشاء والتعديل) */
    public function suggest(Request $request)
    {
        $data = $request->validate([
            'name'    => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        return $this->success([
            'name_en'    => filled($data['name'] ?? null) ? EnglishNameSuggester::companyName($data['name']) : null,
            'address_en' => filled($data['address'] ?? null) ? EnglishNameSuggester::address($data['address']) : null,
        ]);
    }

    /** GET dashboard/contractors/english-names — كل المقاولين مع الاسم/العنوان الإنجليزي الحالي والمقترح */
    public function index(Request $request)
    {
        $rows = Contractor::query()
            ->with(['governorate:id,name', 'cityModel:id,name'])
            ->select(['id', 'membership_number', 'name', 'name_en', 'address_en', 'city', 'city_id', 'governorate_id', 'district', 'address', 'status'])
            ->orderBy('id')
            ->get()
            ->map(function (Contractor $c) {
                $suggested = EnglishNameSuggester::forContractor($c);

                return [
                    'id'                   => $c->id,
                    'membership_number'    => $c->membership_number,
                    'status'               => $c->status,
                    'name'                 => $c->name,
                    'address'              => EnglishNameSuggester::arabicAddress($c),
                    'name_en'              => $c->name_en,
                    'address_en'           => $c->address_en,
                    'suggested_name_en'    => $suggested['name_en'],
                    'suggested_address_en' => $suggested['address_en'],
                ];
            });

        return $this->success([
            'items'           => $rows->values(),
            'missing_name'    => $rows->whereNull('name_en')->count(),
            'missing_address' => $rows->whereNull('address_en')->count(),
        ]);
    }

    /** POST dashboard/contractors/english-names — حفظ جماعي من صفحة المراجعة */
    public function save(Request $request)
    {
        $data = $request->validate([
            'items'              => 'required|array|min:1|max:2000',
            'items.*.id'         => 'required|integer|distinct|exists:contractors,id',
            'items.*.name_en'    => self::NAME_RULE,
            'items.*.address_en' => self::ADDRESS_RULE,
        ], [
            'items.*.name_en.regex'    => 'الاسم الإنجليزي يقبل حروفاً إنجليزية وأرقاماً فقط.',
            'items.*.address_en.regex' => 'العنوان الإنجليزي يقبل حروفاً إنجليزية وأرقاماً فقط.',
        ]);

        $changed = [];
        DB::transaction(function () use ($data, &$changed) {
            $byId = Contractor::whereIn('id', collect($data['items'])->pluck('id'))->get()->keyBy('id');
            foreach ($data['items'] as $item) {
                $c = $byId[$item['id']];
                $c->fill([
                    'name_en'    => filled($item['name_en'] ?? null) ? trim($item['name_en']) : null,
                    'address_en' => filled($item['address_en'] ?? null) ? trim($item['address_en']) : null,
                ]);
                if ($c->isDirty()) {
                    $changed[$c->id] = $c->getDirty();
                    $c->save(); // بيمسح النسخ الإنجليزية القديمة من الشهادات (Contractor::booted)
                }
            }
        });

        if ($changed) {
            AuditLogService::record(Auth::user(), 'contractors.english_names_saved', null, [
                'count'   => count($changed),
                'changes' => $changed,
            ]);
        }

        return $this->success(['updated' => count($changed)], 'تم حفظ ' . count($changed) . ' مقاول.');
    }
}
