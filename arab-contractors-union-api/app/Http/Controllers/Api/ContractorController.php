<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\City;
use App\Models\Contractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ContractorController extends Controller
{
    use ApiResponseTrait;

    // GET /api/contractors
    public function index(Request $request)
    {
        $query = Contractor::query();

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('license_number', 'like', "%{$q}%")
                   ->orWhere('email', 'like', "%{$q}%")
                   ->orWhere('membership_number', 'like', "%{$q}%")
                   ->orWhere('commercial_register', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = (int) $request->get('per_page', 10);

        return $this->paginated($query->latest()->paginate($perPage));
    }

    /**
     * قيد unique على عمود يتجاهل صفوف soft-deleted — بدون whereNull('deleted_at') يمنع
     * قاعدة Laravel الخام إعادة استخدام قيمة (جوال، بريد، رقم عضوية...) لمقاول محذوف سابقاً،
     * لأنها تتحقق من كل الصفوف بالجدول مباشرة بدون المرور بـ global scope الخاص بـ SoftDeletes.
     */
    private function uniqueIgnoringSoftDeleted(string $column, $isUpdate, $contractorId): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('contractors', $column)->whereNull('deleted_at');

        return $isUpdate ? $rule->ignore($contractorId) : $rule;
    }

    private function getValidationRules($isUpdate = false, $contractorId = null)
    {
        // السقف مشتق من UploadLimits لا رقماً ثابتاً: 10240 السابقة كانت تَعِد بـ10MB
        // بينما upload_max_filesize على الخادم 2M، فكل ملف أكبر كان يُسقَط قبل التحقق
        // دون أي رسالة حجم (TASK-16 #2/#3).
        $fileRule = 'nullable|file|mimes:' . implode(',', \App\Support\UploadLimits::ALLOWED_EXTENSIONS)
            . '|max:' . \App\Support\UploadLimits::maxFileKb();

        return [
            'name'                          => 'required|string|max:255',
            'membership_number'             => [
                'required',
                'string',
                $this->uniqueIgnoringSoftDeleted('membership_number', $isUpdate, $contractorId),
                new \App\Rules\MembershipNumber,
            ],
            'commercial_register'           => [
                'required',
                'digits:9',
                $this->uniqueIgnoringSoftDeleted('commercial_register', $isUpdate, $contractorId),
            ],
            'license_number'                => [
                'nullable',
                'digits:5',
                $this->uniqueIgnoringSoftDeleted('license_number', $isUpdate, $contractorId),
            ],
            'status'                        => 'sometimes|in:active,pending,expired,suspended',
            'trade'                         => 'nullable|string|max:100',
            'classification'                => 'nullable|string|max:10',
            'established_year'              => 'nullable|integer|min:1900|max:' . date('Y'),
            'field_lk_type'                 => 'nullable|integer',
            'specialization_lk_type'        => 'nullable|integer',
            'established_date'              => 'nullable|date',
            'specialties'                   => 'nullable|string',
            'owner_name'                    => 'nullable|string|max:255',
            'email'                         => [
                'nullable', 'email',
                $this->uniqueIgnoringSoftDeleted('email', $isUpdate, $contractorId),
            ],
            'phone'                         => [
                'nullable', 'string', 'max:20',
                $this->uniqueIgnoringSoftDeleted('phone', $isUpdate, $contractorId),
            ],
            'city'                          => 'nullable|string|max:100',
            // إلزامية عند الإنشاء فقط (REQ-01 #5) — تبقى nullable عند التعديل حتى لا يُحظر
            // حفظ أي تعديل آخر على مقاولين قدامى لا تملك سجلاتهم هذه الحقول أصلاً بعد.
            'governorate_id'                => ($isUpdate ? 'nullable' : 'required') . '|integer|exists:governorates,id',
            'city_id'                       => ($isUpdate ? 'nullable' : 'required') . '|integer|exists:cities,id',
            'district'                      => ($isUpdate ? 'nullable' : 'required') . '|string|max:100',
            'address'                       => 'nullable|string',
            'notes'                         => 'nullable|string',

            // Text fields
            'partners'                      => 'nullable|string',
            'fax'                           => 'nullable|string|max:50',
            'building'                      => ($isUpdate ? 'nullable' : 'required') . '|string|max:100',
            'floor'                         => ($isUpdate ? 'nullable' : 'required') . '|string|max:50',
            'capital'                       => 'nullable|string|max:100',
            'registration_date'             => 'nullable|date',
            'legal_form'                    => 'nullable|string|max:100',
            'company_purposes'              => 'nullable|string',
            'authorized_person'             => 'nullable|string|max:255',
            'authorized_person_id_number'   => 'nullable|string|max:50',
            'authorized_person_phone'       => 'nullable|string|max:20',
            'authorized_person_whatsapp'    => 'nullable|string|max:20',

            // Files
            'cr_file'                       => $fileRule,
            'id_file'                       => $fileRule,
            'lease_or_ownership_contract'   => $fileRule,
            'company_approval_letter'       => $fileRule,
            'municipal_license'             => $fileRule,
            'company_register'              => $fileRule,
            'articles_of_association'       => $fileRule,
            'internal_bylaws'               => $fileRule,
            'bank_dealing_letter'           => $fileRule,
            'secretary_contract'            => $fileRule,
            'full_time_engineer_certificate'=> $fileRule,
            'accountant_certificate_or_contract' => $fileRule,
            'partners_ids'                  => $fileRule,
            'authorization_letter'          => $fileRule,

            // حذف مستندات قائمة (TASK-16 #4) — محصور بمفاتيح FILE_FIELDS حتى لا يُمرَّر
            // اسم عمود عشوائي فيُفرَّغ من قاعدة البيانات.
            'remove_documents'              => 'nullable|array',
            'remove_documents.*'            => ['string', Rule::in(array_keys(self::FILE_FIELDS))],
        ];
    }

    /**
     * حقول المستندات ومسار تخزين كلٍّ منها. كانت مصفوفة محلية داخل handleFileUploads()،
     * ورُفعت لثابت ليشتقّ منه قيد remove_documents قائمته البيضاء من نفس المصدر
     * بدل تكرار الأسماء الأربعة عشر مرتين.
     */
    private const FILE_FIELDS = [
        'cr_file'                       => 'contractors/cr',
        'id_file'                       => 'contractors/id',
        'lease_or_ownership_contract'   => 'contractors/leases',
        'company_approval_letter'       => 'contractors/approvals',
        'municipal_license'             => 'contractors/licenses',
        'company_register'              => 'contractors/registers',
        'articles_of_association'       => 'contractors/articles',
        'internal_bylaws'               => 'contractors/bylaws',
        'bank_dealing_letter'           => 'contractors/bank_letters',
        'secretary_contract'            => 'contractors/secretary_contracts',
        'full_time_engineer_certificate'=> 'contractors/engineer_certs',
        'accountant_certificate_or_contract' => 'contractors/accountant_certs',
        'partners_ids'                  => 'contractors/partners_ids',
        'authorization_letter'          => 'contractors/authorization_letters',
    ];

    private function handleFileUploads(Request $request, &$validated, $contractor = null)
    {
        $uploaded = [];

        foreach (self::FILE_FIELDS as $field => $path) {
            if ($request->hasFile($field)) {
                if ($contractor && $contractor->$field) {
                    Storage::disk('public')->delete($contractor->$field);
                }
                $validated[$field] = $request->file($field)->store($path, 'public');
                $uploaded[] = $field;
            }
        }

        $this->handleDocumentRemovals($request, $validated, $contractor, $uploaded);
    }

    /**
     * حذف مستندات صراحةً عبر remove_documents[] (TASK-16 #4) — سابقاً كان الاستبدال
     * هو السبيل الوحيد لإزالة مستند.
     *
     * قائمة صريحة عمداً لا "القيمة الفارغة تعني الحذف": VFileInput يُرجع [] عند تفريغه،
     * وتفسير الفراغ كحذف يُعيد كسر إصلاح TASK-01 #4 الذي أوقف إرسال تلك المصفوفة أصلاً.
     */
    private function handleDocumentRemovals(Request $request, &$validated, $contractor, array $uploaded): void
    {
        // ليس عموداً في الجدول — يُزال صراحةً بدل الاتّكال على حماية mass-assignment وحدها.
        unset($validated['remove_documents']);

        if (! $contractor) {
            return;
        }

        foreach ((array) $request->input('remove_documents', []) as $field) {
            // رفعُ ملف جديد لنفس الحقل في الطلب ذاته يتقدّم على علامة حذف قديمة —
            // الاستبدال حذف أصلاً للملف السابق أعلاه، وتطبيق الحذف بعده يمسح الجديد.
            if (! isset(self::FILE_FIELDS[$field]) || in_array($field, $uploaded, true)) {
                continue;
            }

            if ($contractor->$field) {
                Storage::disk('public')->delete($contractor->$field);
            }

            $validated[$field] = null;
        }
    }

    private function getValidationMessages()
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'string'   => 'يجب أن يكون حقل :attribute نصاً.',
            'max'      => [
                'numeric' => 'يجب ألا يكون حقل :attribute أكبر من :max.',
                'file'    => 'يجب ألا يتجاوز حجم ملف :attribute :max كيلوبايت.',
                'string'  => 'يجب ألا يتجاوز حقل :attribute :max حرف.',
                'array'   => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عناصر.',
            ],
            'unique'   => 'قيمة :attribute هذه مستخدمة مسبقاً (مسجلة لدينا).',
            'email'    => 'يجب أن يكون حقل :attribute عنوان بريد إلكتروني صالحاً.',
            'date'     => 'حقل :attribute ليس تاريخاً صحيحاً.',
            'integer'  => 'يجب أن يكون حقل :attribute رقماً صحيحاً.',
            'min'      => [
                'numeric' => 'يجب أن يكون حقل :attribute على الأقل :min.',
            ],
            'file'     => 'يجب أن يكون حقل :attribute ملفاً.',
            'image'    => 'يجب أن يكون حقل :attribute صورة.',
            'mimes'    => 'يجب أن يكون ملف :attribute من نوع: :values.',
        ];
    }

    private function getValidationAttributes()
    {
        return [
            'name'                          => 'الاسم',
            'membership_number'             => 'رقم العضوية',
            'commercial_register'           => 'رقم السجل التجاري',
            'license_number'                => 'رقم الترخيص',
            'status'                        => 'الحالة',
            'trade'                         => 'التخصص',
            'classification'                => 'التصنيف',
            'established_year'              => 'سنة التأسيس',
            'owner_name'                    => 'اسم المالك',
            'email'                         => 'البريد الإلكتروني',
            'phone'                         => 'الجوال',
            'city'                          => 'المدينة',
            'governorate_id'                => 'المحافظة',
            'city_id'                       => 'المدينة',
            'district'                      => 'الحي',
            'address'                       => 'العنوان',
            'partners'                      => 'الشركاء',
            'fax'                           => 'الفاكس',
            'building'                      => 'العمارة',
            'floor'                         => 'الطابق',
            'capital'                       => 'رأس المال',
            'registration_date'             => 'تاريخ التسجيل',
            'legal_form'                    => 'الشكل القانوني',
            'company_purposes'              => 'غايات الشركة',
            'authorized_person'             => 'المفوض بالتوقيع',
            'authorized_person_id_number'   => 'رقم هوية المفوض',
            'authorized_person_phone'       => 'رقم جوال المفوض',
            'authorized_person_whatsapp'    => 'رقم واتساب المفوض',
            'cr_file'                       => 'ملف السجل التجاري',
            'id_file'                       => 'ملف الهوية',
            'authorized_signature'          => 'نموذج التوقيع',
            'lease_or_ownership_contract'   => 'عقد الإيجار أو الملكية',
            'company_approval_letter'       => 'كتاب الموافقة',
            'municipal_license'             => 'رخصة المهن',
            'company_register'              => 'مستخرج السجل',
            'articles_of_association'       => 'عقد التأسيس',
            'internal_bylaws'               => 'النظام الداخلي',
            'bank_dealing_letter'           => 'كتاب البنك',
            'secretary_contract'            => 'عقد السكرتير',
            'full_time_engineer_certificate'=> 'شهادة المهندس المتفرغ',
            'accountant_certificate_or_contract' => 'شهادة تفرغ المحاسب / عقد المكتب المحاسبي',
            'partners_ids'                  => 'هويات الشركاء',
            'authorization_letter'          => 'كتاب التفويض',
        ];
    }

    /** مزامنة المحافظة والعمود النصي القديم `city` عند الإرسال بالـ ID */
    private function syncLocation(array &$validated): void
    {
        if (! empty($validated['city_id'])) {
            $city = City::find($validated['city_id']);
            if ($city) {
                $validated['governorate_id'] = $city->governorate_id;
                $validated['city']           = $city->name;
            }
        }
    }

    // POST /api/contractors
    public function store(Request $request)
    {
        $validated = $request->validate($this->getValidationRules(), $this->getValidationMessages(), $this->getValidationAttributes());
        $this->handleFileUploads($request, $validated);
        $this->syncLocation($validated);

        if (isset($validated['specialties'])) {
            $validated['specialties'] = json_decode($validated['specialties'], true);
        }

        $contractor = Contractor::create($validated);

        return $this->success($contractor->toArray(), 'تم إضافة المقاول بنجاح.', 201);
    }

    // GET /api/contractors/{id}
    public function show(Contractor $contractor)
    {
        $contractor->withFileUrls = true;

        return $this->success($contractor->load(['activeMembership', 'governorate:id,name'])->toArray());
    }

    // PUT/PATCH /api/contractors/{id}
    public function update(Request $request, Contractor $contractor)
    {
        $validated = $request->validate($this->getValidationRules(true, $contractor->id), $this->getValidationMessages(), $this->getValidationAttributes());
        $this->handleFileUploads($request, $validated, $contractor);
        $this->syncLocation($validated);

        if (isset($validated['specialties'])) {
            $validated['specialties'] = json_decode($validated['specialties'], true);
        }
        
        $contractor->update($validated);
        $contractor->withFileUrls = true;

        return $this->success($contractor->toArray(), 'تم تحديث بيانات المقاول بنجاح.');
    }

    // DELETE /api/contractors/{id}
    public function destroy(Contractor $contractor)
    {
        // الأعمدة الفريدة (phone, email, license_number, commercial_register, membership_number)
        // تبقى بجدول contractors بعد الحذف الناعم وتصطدم بقيد unique عند إعادة تسجيل نفس البيانات —
        // نلحق بها لاحقة بمعرّف السجل حتى تتحرر القيمة الأصلية لإعادة الاستخدام مع بقاء أثرها بالسجل المؤرشف.
        $suffix = '_deleted_' . $contractor->id;
        $mangled = collect(['phone', 'email', 'license_number', 'commercial_register', 'membership_number'])
            ->mapWithKeys(fn ($field) => [$field => $contractor->{$field} ? $contractor->{$field} . $suffix : null])
            ->all();

        $contractor->update($mangled);
        $contractor->delete();

        return $this->success(message: 'تم حذف المقاول بنجاح.');
    }

    // PATCH /api/contractors/{id}/status — تغيير سريع للحالة (نشط/معلّق/موقوف/منتهي) بدون المرور بفورم الملف الكامل
    public function changeStatus(Request $request, Contractor $contractor)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,pending,expired,suspended',
        ]);

        $contractor->update($validated);

        return $this->success($contractor->fresh()->toArray(), 'تم تحديث حالة المقاول بنجاح.');
    }

    /**
     * PATCH /api/contractors/{id}/freeze — تجميد/رفع تجميد الحساب.
     * منفصل عن status (نشط/معلّق/موقوف/منتهي): التجميد يقفل الدخول للتطبيق
     * بالكامل، بينما status=suspended يمنع تجديد العضوية فقط ولا يقفل الدخول
     * (راجع Contractor::loginEligibility و ContractorRequirements::renewalBlockers).
     */
    public function freeze(Request $request, Contractor $contractor)
    {
        $data = $request->validate(['frozen' => 'required|boolean']);

        $contractor->update(['is_frozen' => $data['frozen']]);

        // تجميد فوري: نُبطل كل التوكنات الآن بدل الانتظار لأول طلب تالٍ يمر
        // عبر EnsureContractorIsActive — بدون هذا يقدر يستمر يستخدم التطبيق
        // بالتوكن الحالي لحد ما يعمل طلب جديد.
        if ($data['frozen']) {
            $contractor->tokens()->delete();
        }

        \App\Services\AuditLogService::record(
            $request->user(),
            $data['frozen'] ? 'contractor.frozen' : 'contractor.unfrozen',
            $contractor,
        );

        return $this->success(
            ['is_frozen' => $contractor->is_frozen],
            $data['frozen'] ? 'تم تجميد حساب المقاول.' : 'تم رفع التجميد عن حساب المقاول.',
        );
    }

    // PATCH /api/contractors/{id}/contact — تعديل سريع لاسم المفوض ورقم التواصل من شاشة
    // "عرض" (أيقونة العين) بدون المرور بفورم الملف الكامل (يتطلب حقولاً إلزامية أخرى)
    public function updateContact(Request $request, Contractor $contractor)
    {
        $validated = $request->validate([
            'authorized_person' => 'nullable|string|max:255',
            'authorized_person_id_number' => 'nullable|string|max:50',
            'authorized_person_phone'     => 'nullable|string|max:20',
            'authorized_person_whatsapp'  => 'nullable|string|max:20',
            'phone'              => [
                'nullable', 'string', 'max:20',
                $this->uniqueIgnoringSoftDeleted('phone', true, $contractor->id),
            ],
        ], $this->getValidationMessages());

        $contractor->update($validated);

        return $this->success($contractor->fresh()->toArray(), 'تم تحديث بيانات التواصل بنجاح.');
    }

    public function nextMembershipNumber()
    {
        return $this->success(
            ['next_membership_number' => Contractor::nextMembershipNumber()],
            'تم توليد رقم العضوية التالي بنجاح',
        );
    }

    // POST /api/contractors/{id}/qr
    public function generateQR(Contractor $contractor)
    {
        $expiresAt = now()->addHours(24);

        $payload = Crypt::encryptString(json_encode([
            'contractor_id' => $contractor->id,
            'name'          => $contractor->name,
            'license'       => $contractor->license_number,
            'expires_at'    => $expiresAt->toISOString(),
        ]));

        $qrSvg = base64_encode(QrCode::format('svg')->size(300)->generate($payload));
        $qrUrl = 'data:image/svg+xml;base64,' . $qrSvg;

        return $this->success([
            'contractor_id' => $contractor->id,
            'token'         => $payload,
            'qr_url'        => $qrUrl,
            'expires_at'    => $expiresAt->toISOString(),
        ]);
    }

    /**
     * PATCH /api/v1/dashboard/contractors/{contractor}/equipment-ban
     * حظر/رفع حظر مقاول من النشر بسوق الآليات فقط — منفصل عن status/is_frozen العامين (REQ-08).
     */
    public function equipmentBan(Request $request, Contractor $contractor)
    {
        $data = $request->validate(['banned' => 'required|boolean']);

        $contractor->update(['equipment_banned_at' => $data['banned'] ? now() : null]);

        \App\Services\AuditLogService::record(
            $request->user(),
            $data['banned'] ? 'contractor.equipment_banned' : 'contractor.equipment_unbanned',
            $contractor,
        );

        return $this->success(
            ['equipment_banned_at' => $contractor->equipment_banned_at],
            $data['banned'] ? 'تم حظر المقاول من سوق الآليات.' : 'تم رفع الحظر عن المقاول.',
        );
    }
}
