<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\CertificateRequest;
use App\Models\User;
use App\Notifications\CertificateRequestStatusNotification;
use App\Notifications\CertificateRequestSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class CertificateRequestController extends Controller
{
    use ApiResponseTrait;

    /** تنسيق طلب شهادة لاستجابة الـ API */
    private function format(CertificateRequest $r): array
    {
        return [
            'id'              => $r->id,
            'contractor_id'   => $r->contractor_id,
            'contractor'      => $r->contractor?->name,
            'membership_number' => $r->contractor?->membership_number,
            'type'            => $r->type,
            'type_label'      => $r->type_label,
            'status'          => $r->status,
            'status_label'    => $r->status_label,
            'notes'           => $r->notes,
            'attachment_url'  => $r->attachment_url,
            'reject_reason'   => $r->reject_reason,
            'certificate_url' => $r->certificate_url,
            'request_date'    => $r->created_at,
            'issue_date'      => $r->issued_at,
            'reviewed_by'     => $r->reviewedBy?->name,
        ];
    }

    /**
     * يحسب المتطلبات التي يجب على المقاول تسويتها قبل طلب الشهادة:
     * غرامات غير مدفوعة، حساب مجمّد، أو عدم وجود عضوية نشطة.
     */
    private function requirementIssues($contractor): array
    {
        return \App\Support\ContractorRequirements::issues($contractor);
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — شاشة طلب شهادة العضوية
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/v1/contractor/certificate-requests
     * بيانات المقاول + المتطلبات + طلبات الشهادات السابقة.
     */
    public function index(Request $request)
    {
        $contractor = $request->user();
        $issues     = $this->requirementIssues($contractor);

        // نفس استثناء unpaid_dues المطبَّق بـstore()/certificatesStatus() — شهادة العضوية
        // محكومة بهامش السماح لا بالحظر الصفري، وcan_request هنا يغذّي زر الطلب بالموبايل
        $blockingIssues = array_values(array_filter($issues, fn ($i) => $i['type'] !== 'unpaid_dues'));

        return $this->success([
            'contractor' => [
                'name'              => $contractor->name,
                'membership_number' => $contractor->membership_number,
                'status'            => $contractor->status,
                'is_frozen'         => (bool) $contractor->is_frozen,
            ],
            'can_request'             => count($blockingIssues) === 0 && $contractor->profile_data_complete,
            'requirement_issues'      => $issues,
            'profile_data_complete'   => $contractor->profile_data_complete,
            'missing_profile_fields'  => $contractor->missing_profile_fields,
            'requests'           => $contractor->certificateRequests()
                ->latest()
                ->get()
                ->map(fn ($r) => $this->format($r))
                ->values(),
        ]);
    }

    /**
     * GET /api/v1/contractor/certificates/status
     * ملخّص موحّد لحالة شهادتي العضوية والتصنيف — يغذّي شاشة "طلب شهادة" بالبطاقتين المتجاورتين.
     */
    public function certificatesStatus(Request $request)
    {
        $contractor = $request->user();
        $issues     = $this->requirementIssues($contractor);
        $percent    = $contractor->currentYearDuesPaidPercentage();

        $latestMembershipCert = $contractor->certificateRequests()
            ->where('type', 'membership')
            ->latest()
            ->first();

        $latestClassificationCert = $contractor->certificateRequests()
            ->where('type', 'classification')
            ->latest()
            ->first();

        $activeMembership = $contractor->activeMembership;

        // نفس استثناء unpaid_dues المطبَّق بـstore() — شهادة العضوية محكومة بهامش السماح لا بالحظر الصفري
        $blockingIssues = array_values(array_filter($issues, fn ($i) => $i['type'] !== 'unpaid_dues'));

        return $this->success([
            'membership' => [
                'eligible'          => count($blockingIssues) === 0
                    && $contractor->profile_data_complete
                    && $contractor->isEligibleForMembershipCertificate(),
                'paid_percentage'   => $percent,
                'required_percent'  => \App\Models\Contractor::MEMBERSHIP_CERT_MIN_PAID_PERCENT,
                'remaining_to_95_jod' => $contractor->remainingToReach95PercentJod(),
                'current_year'      => now()->year,
                'outstanding_jod'   => $contractor->outstandingDuesTotal(),
                'requirement_issues' => $issues,
                'profile_data_complete' => $contractor->profile_data_complete,
                'membership_valid_until' => $activeMembership?->expires_at?->toDateString(),
                'is_expired'        => $activeMembership ? $activeMembership->expires_at?->isPast() : true,
                'latest_request'    => $latestMembershipCert ? $this->format($latestMembershipCert) : null,
            ],
            'classification' => [
                'has_certificate' => (bool) $latestClassificationCert?->certificate_path,
                'certificate_url' => $latestClassificationCert?->certificate_url,
                'latest_request'  => $latestClassificationCert ? $this->format($latestClassificationCert) : null,
                'message'         => $latestClassificationCert?->certificate_path
                    ? null
                    : 'يرجى مراجعة مقر اتحاد المقاولين لطلب شهادة التصنيف.',
            ],
        ]);
    }

    /**
     * POST /api/v1/contractor/certificate-requests
     * يقدّم المقاول طلب شهادة جديد (يُرفض إن كانت هناك متطلبات غير مُسوّاة).
     */
    public function store(Request $request)
    {
        $contractor = $request->user();

        // يقبل JSON أو multipart/form-data — المرفق اختياري
        $data = $request->validate([
            'type'       => 'required|in:membership,good_standing,classification,experience',
            'notes'      => 'nullable|string|max:500',
            'attachment' => 'nullable|file|mimes:png,jpg,jpeg,webp,pdf|max:5120',
        ]);

        if (! $contractor->profile_data_complete) {
            return $this->error(
                'يجب إكمال بيانات الملف الشخصي قبل تقديم طلب شهادة.',
                403,
                ['missing_profile_fields' => $contractor->missing_profile_fields],
                'profile_incomplete',
            );
        }

        $issues = $this->requirementIssues($contractor);

        // شهادة العضوية تحديداً محكومة بهامش السماح الخاص بها (isEligibleForMembershipCertificate)
        // بدل الحظر الثنائي الصفري لـ"unpaid_dues" — نستبعده هنا فقط لهذا النوع، ونتحقق منه
        // بدقة بالخطوة التالية. باقي المتطلبات (غرامات، تجميد، عدم وجود عضوية نشطة) تبقى حاجزة كما هي.
        $blockingIssues = $data['type'] === 'membership'
            ? array_values(array_filter($issues, fn ($i) => $i['type'] !== 'unpaid_dues'))
            : $issues;

        if (count($blockingIssues) > 0) {
            return $this->error(
                'لا يمكن تقديم الطلب قبل تسوية المتطلبات المستحقّة.',
                403,
                null,
                'requirements_pending',
            );
        }

        // شهادة العضوية تحديداً تشترط بلوغ نسبة سداد ذمم السنة الحالية 95% (محرك الاحتساب الآلي — REQ-02)
        if ($data['type'] === 'membership' && ! $contractor->isEligibleForMembershipCertificate()) {
            $percent   = $contractor->currentYearDuesPaidPercentage();
            $remaining = $contractor->remainingToReach95PercentJod();
            $outstanding = $contractor->outstandingDuesTotal();

            return $this->error(
                "يتبقى لك سداد {$remaining} دينار للوصول إلى حد الـ 95% واستخراج شهادتك تلقائياً.",
                403,
                [
                    'paid_percentage'   => $percent,
                    'outstanding_jod'   => $outstanding,
                    'remaining_to_95_jod' => $remaining,
                    'current_year'      => now()->year,
                    'remaining_dues'   => $contractor->dues()->outstanding()->get()->map(fn ($d) => [
                        'id'           => $d->id,
                        'description'  => $d->description,
                        'year'         => $d->year,
                        'amount_jod'   => $d->amount_jod,
                        'remaining_jod' => $d->remaining_jod,
                        'status'       => $d->status,
                    ])->values(),
                ],
                'dues_below_threshold',
            );
        }

        // منع تكرار طلب من نفس النوع ما زال قيد المعالجة
        $duplicate = $contractor->certificateRequests()
            ->where('type', $data['type'])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($duplicate) {
            return $this->error('لديك طلب سابق من نفس النوع قيد المعالجة، يرجى انتظار الرد عليه.', 422);
        }

        $attachmentPath = $request->hasFile('attachment')
            ? $request->file('attachment')->store('certificate-attachments', 'public')
            : null;

        $certRequest = $contractor->certificateRequests()->create([
            'type'       => $data['type'],
            'notes'      => $data['notes'] ?? null,
            'attachment' => $attachmentPath,
            'status'     => 'pending',
        ]);

        // تم إلغاء الإصدار التلقائي بناء على طلب المستخدم، لتصبح جميع الطلبات بانتظار موافقة الإدارة

        // إشعار الإدارة بوجود طلب جديد (شهادة التصنيف — تبقى تحتاج مراجعة/رفع ملف يدوي)
        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new CertificateRequestSubmittedNotification($certRequest));
        }

        return $this->success(
            $this->format($certRequest),
            'تم تقديم طلب الشهادة بنجاح، وسيتم إشعارك عند إصدارها.',
            201,
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/dashboard/certificate-requests */
    public function adminIndex(Request $request)
    {
        $query = CertificateRequest::with(['contractor:id,name,membership_number', 'reviewedBy:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->whereHas('contractor', fn ($c) =>
                $c->where('name', 'like', "%{$q}%")
                  ->orWhere('membership_number', 'like', "%{$q}%")
            );
        }

        $paginator = $query->latest()->paginate(15)->through(fn ($r) => $this->format($r));

        return $this->paginated($paginator);
    }

    /** GET /api/v1/dashboard/certificate-requests/{certificateRequest} */
    public function show(CertificateRequest $certificateRequest)
    {
        $certificateRequest->load(['contractor', 'reviewedBy:id,name']);

        $data = $this->format($certificateRequest);
        $data['requirement_issues'] = $certificateRequest->contractor
            ? $this->requirementIssues($certificateRequest->contractor)
            : [];

        return $this->success($data);
    }

    /** POST /api/v1/dashboard/certificate-requests/{certificateRequest}/approve */
    public function approve(CertificateRequest $certificateRequest)
    {
        $certificateRequest->update([
            'status'        => 'approved',
            'reject_reason' => null,
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => now(),
        ]);

        $certificateRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certificateRequest)
        );

        return $this->success($this->format($certificateRequest->fresh()), 'تمت الموافقة على الطلب.');
    }

    /** POST /api/v1/dashboard/certificate-requests/{certificateRequest}/reject */
    public function reject(Request $request, CertificateRequest $certificateRequest)
    {
        $data = $request->validate([
            'reject_reason' => 'required|string|max:2000',
        ]);

        $certificateRequest->update([
            'status'        => 'rejected',
            'reject_reason' => $data['reject_reason'],
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => now(),
        ]);

        $certificateRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certificateRequest)
        );

        return $this->success($this->format($certificateRequest->fresh()), 'تم رفض الطلب.');
    }

    /**
     * POST /api/v1/dashboard/certificate-requests/{certificateRequest}/issue
     * رفع ملف الشهادة وإصدارها للمقاول.
     */
    public function issue(Request $request, CertificateRequest $certificateRequest)
    {
        $request->validate([
            'certificate' => 'required|file|mimes:pdf|max:10240', // 10 MB
        ]);

        // استبدال الملف السابق إن وُجد
        if ($certificateRequest->certificate_path) {
            Storage::disk('public')->delete($certificateRequest->certificate_path);
        }

        $path = $request->file('certificate')->store('certificates/membership', 'public');

        $certificateRequest->update([
            'status'           => 'issued',
            'certificate_path' => $path,
            'issued_at'        => now(),
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        $certificateRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certificateRequest)
        );

        return $this->success($this->format($certificateRequest->fresh()), 'تم إصدار الشهادة بنجاح.');
    }

    /** DELETE /api/v1/dashboard/certificate-requests/{certificateRequest} */
    public function destroy(CertificateRequest $certificateRequest)
    {
        if ($certificateRequest->certificate_path) {
            Storage::disk('public')->delete($certificateRequest->certificate_path);
        }

        $certificateRequest->delete();

        return $this->success(message: 'تم حذف الطلب.');
    }

    /**
     * POST /api/v1/dashboard/certificate-requests/bulk-delete
     * حذف مجموعة طلبات دفعة واحدة مع ملفاتها.
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'integer|exists:certificate_requests,id',
        ]);

        $requests = CertificateRequest::whereIn('id', $data['ids'])->get();

        foreach ($requests as $r) {
            if ($r->certificate_path) {
                Storage::disk('public')->delete($r->certificate_path);
            }
            $r->delete();
        }

        \App\Services\AuditLogService::record(
            Auth::user(),
            'certificate.bulk_deleted',
            null,
            ['ids' => $requests->pluck('id')->all()]
        );

        return $this->success(
            ['deleted' => $requests->count()],
            "تم حذف {$requests->count()} طلب.",
        );
    }

    /**
     * POST /api/v1/dashboard/certificate-requests/{certificateRequest}/regenerate
     * يعيد توليد ملف شهادة العضوية لطلب قائم مع الحفاظ على رقمه التسلسلي.
     * لا يُرسل إشعاراً للمقاول — إعادة التوليد إجراء إداري لتصحيح الملف.
     */
    public function regenerate(Request $request, CertificateRequest $certificateRequest)
    {
        if ($certificateRequest->type !== 'membership') {
            return $this->error('إعادة الإصدار متاحة لشهادات العضوية فقط.', 422);
        }

        $data = $request->validate([
            'address'         => 'nullable|string|max:255',
            'decision_number' => 'nullable|string|max:100',
            'decision_date'   => 'nullable|date',
        ]);

        $oldPath = $certificateRequest->certificate_path;

        $path = app(\App\Services\MembershipCertificatePdfService::class)->generate($certificateRequest, [
            'address'         => $data['address'] ?? null,
            'decision_number' => $data['decision_number'] ?? null,
            'decision_date'   => $data['decision_date'] ?? null,
        ]);

        // الرقم التسلسلي مشتق من معرّف الطلب، فالمسار الجديد مطابق للقديم عملياً.
        // نحذف القديم فقط لو اختلف حتى لا نمسح الملف الذي تولّد للتو.
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $certificateRequest->update([
            'status'           => 'issued',
            'certificate_path' => $path,
            'issued_at'        => now(),
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        \App\Services\AuditLogService::record(
            Auth::user(),
            'certificate.regenerated',
            $certificateRequest,
            ['contractor_id' => $certificateRequest->contractor_id]
        );

        return $this->success($this->format($certificateRequest->fresh()), 'تمت إعادة إصدار الشهادة بنجاح.');
    }

    /**
     * POST /api/v1/dashboard/certificate-requests/issue-membership
     * يُصدر المشرف شهادة عضوية مباشرة لمقاول معيَّن بدون طلب مسبق من المقاول.
     */
    public function adminIssueMembership(Request $request)
    {
        $data = $request->validate([
            'contractor_id'   => 'required|integer|exists:contractors,id',
            'notes'           => 'nullable|string|max:500',
            'address'         => 'nullable|string|max:255',
            'decision_number' => 'nullable|string|max:100',
            'decision_date'   => 'nullable|date',
        ]);

        $contractor = \App\Models\Contractor::findOrFail($data['contractor_id']);

        // إنشاء طلب شهادة عضوية
        $certRequest = $contractor->certificateRequests()->create([
            'type'   => 'membership',
            'notes'  => $data['notes'] ?? 'إصدار مباشر من لوحة التحكم',
            'status' => 'pending',
        ]);

        // توليد PDF تلقائياً — عنوان الشركة ورقم/تاريخ قرار التصنيف قابلة للتعديل يدوياً قبل الإصدار
        try {
            $path = app(\App\Services\MembershipCertificatePdfService::class)->generate($certRequest, [
                'address'         => $data['address'] ?? null,
                'decision_number' => $data['decision_number'] ?? null,
                'decision_date'   => $data['decision_date'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // لا نترك طلب "قيد المراجعة" يتيم بدون شهادة عند فشل التوليد
            $certRequest->delete();
            throw $e;
        }

        $certRequest->update([
            'status'           => 'issued',
            'certificate_path' => $path,
            'issued_at'        => now(),
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
        ]);

        // إشعار المقاول
        $certRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certRequest)
        );

        // سجل تدقيق
        \App\Services\AuditLogService::record(
            Auth::user(),
            'certificate.admin_issued_membership',
            $certRequest,
            ['contractor_id' => $contractor->id, 'contractor_name' => $contractor->name]
        );

        return $this->success(
            $this->format($certRequest->fresh()),
            'تم إصدار شهادة العضوية بنجاح.',
            201,
        );
    }

    /**
     * GET /api/v1/certificates/verify/{token}
     * تحقق عام (بدون تسجيل دخول) من صحة شهادة عبر رمز QR — لا يكشف أي بيانات مالية.
     */
    public function verify(string $token)
    {
        try {
            $payload = json_decode(\Illuminate\Support\Facades\Crypt::decryptString(urldecode($token)), true);
            $certRequest = CertificateRequest::find($payload['id'] ?? null);
        } catch (\Throwable $e) {
            $certRequest = null;
        }

        if (! $certRequest || $certRequest->status !== 'issued') {
            return $this->success(['valid' => false]);
        }

        $serial = 'MC-' . str_pad((string) $certRequest->id, 6, '0', STR_PAD_LEFT);

        return $this->success([
            'valid'                   => true,
            'contractor_name'         => $certRequest->contractor?->name,
            'membership_number'       => $certRequest->contractor?->membership_number,
            'type_label'              => $certRequest->type_label,
            'serial'                  => $serial,
            'issued_at'               => $certRequest->issued_at,
            'membership_valid_until'  => $certRequest->contractor?->activeMembership?->expires_at?->toDateString(),
        ]);
    }
}
