<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\CertificateRequest;
use App\Models\User;
use App\Models\Contractor;
use App\Notifications\CertificateRequestStatusNotification;
use App\Notifications\CertificateRequestSubmittedNotification;
use App\Http\Requests\Certificate\StoreCertificateRequestRequest;
use App\Http\Requests\Certificate\RejectCertificateRequestRequest;
use App\Http\Requests\Certificate\IssueCertificateRequestRequest;
use App\Http\Requests\Certificate\BulkDestroyCertificateRequestsRequest;
use App\Http\Requests\Certificate\RegenerateCertificateRequestRequest;
use App\Http\Requests\Certificate\AdminIssueMembershipCertificateRequest;
use App\Http\Resources\CertificateRequestResource;
use App\Services\CertificateEligibilityService;
use App\Services\MembershipCertificatePdfService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Crypt;

class CertificateRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private CertificateEligibilityService $eligibilityService,
        private MembershipCertificatePdfService $pdfService,
        private \App\Services\ContractorFinancialService $financialService
    ) {}

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — شاشة طلب شهادة العضوية
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/contractor/certificate-requests */
    public function index(Request $request)
    {
        $contractor = $request->user();
        $issues     = $this->eligibilityService->getIssues($contractor);
        $blockingIssues = $this->eligibilityService->getBlockingIssues($contractor, 'membership');

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
                ->map(fn ($r) => new CertificateRequestResource($r))
                ->values(),
        ]);
    }

    /** GET /api/v1/contractor/certificates/status */
    public function certificatesStatus(Request $request)
    {
        $contractor = $request->user();
        $issues     = $this->eligibilityService->getIssues($contractor);
        $percent    = $this->financialService->currentYearDuesPaidPercentage($contractor);

        $latestMembershipCert = $contractor->certificateRequests()
            ->where('type', 'membership')
            ->latest()
            ->first();

        $latestClassificationCert = $contractor->certificateRequests()
            ->where('type', 'classification')
            ->latest()
            ->first();

        $activeMembership = $contractor->activeMembership;
        $displayedActive  = \App\Support\ContractorRequirements::displayedStatusIsActive($contractor);
        $rowValid         = (bool) $activeMembership?->expires_at?->isFuture();
        $blockingIssues = $this->eligibilityService->getBlockingIssues($contractor, 'membership');

        return $this->success([
            'membership' => [
                'eligible'          => count($blockingIssues) === 0
                    && $contractor->profile_data_complete
                    && $this->financialService->isEligibleForMembershipCertificate($contractor),
                'paid_percentage'   => $percent,
                'required_percent'  => \App\Services\ContractorFinancialService::MEMBERSHIP_CERT_MIN_PAID_PERCENT,
                'remaining_to_95_jod' => $this->financialService->remainingToReach95PercentJod($contractor),
                'current_year'      => now()->year,
                'outstanding_jod'   => $this->financialService->outstandingDuesTotal($contractor),
                'requirement_issues' => $issues,
                'profile_data_complete' => $contractor->profile_data_complete,
                // نفس الحالة المعروضة للمقاول: الفعّال بدون سجل عضوية ساري بتنتهي عضويته 31/12 من السنة
                'membership_valid_until' => $rowValid
                    ? $activeMembership->expires_at->toDateString()
                    : ($displayedActive ? now()->endOfYear()->toDateString() : $activeMembership?->expires_at?->toDateString()),
                'is_expired'        => ! ($rowValid || $displayedActive),
                'latest_request'    => $latestMembershipCert ? new CertificateRequestResource($latestMembershipCert) : null,
            ],
            'classification' => [
                'has_certificate' => (bool) $latestClassificationCert?->certificate_path,
                'certificate_url' => $latestClassificationCert?->tracked_certificate_url,
                'latest_request'  => $latestClassificationCert ? new CertificateRequestResource($latestClassificationCert) : null,
                'message'         => $latestClassificationCert?->certificate_path
                    ? null
                    : 'يرجى مراجعة مقر اتحاد المقاولين لطلب شهادة التصنيف.',
            ],
        ]);
    }

    /** POST /api/v1/contractor/certificate-requests */
    public function store(StoreCertificateRequestRequest $request)
    {
        $contractor = $request->user();
        $data = $request->validated();

        $eligibility = $this->eligibilityService->checkEligibility($contractor, $data['type']);
        
        if (! $eligibility['eligible']) {
            return $this->error($eligibility['reason'], 403, $eligibility['context'] ?? null, $eligibility['code'] ?? null);
        }

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
            // يُملأ فقط إن سُمح بالتقديم بانتظار اعتماد دفعة الرسوم (TASK-17 #5)
            'pending_payment_id' => $eligibility['pending_payment']->id ?? null,
        ]);

        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new CertificateRequestSubmittedNotification($certRequest));
        }

        return $this->success(
            new CertificateRequestResource($certRequest),
            $certRequest->pending_payment_id
                ? 'تم تقديم طلب الشهادة بنجاح. الطلب بانتظار اعتماد دفعة الرسوم، وسيتم إشعارك عند إصدار الشهادة.'
                : 'تم تقديم طلب الشهادة بنجاح، وسيتم إشعارك عند إصدارها.',
            201,
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/dashboard/certificate-requests */
    public function adminIndex(Request $request)
    {
        $query = CertificateRequest::with(['contractor:id,name,membership_number', 'reviewedBy:id,name', 'pendingPayment']);

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

        $paginator = $query->latest()->paginate(15)->through(fn ($r) => new CertificateRequestResource($r));

        return $this->paginated($paginator);
    }

    /** GET /api/v1/dashboard/certificate-requests/{certificateRequest} */
    public function show(CertificateRequest $certificateRequest)
    {
        $certificateRequest->load(['contractor', 'reviewedBy:id,name', 'pendingPayment']);

        $data = (new CertificateRequestResource($certificateRequest))->resolve();
        $data['requirement_issues'] = $certificateRequest->contractor
            ? $this->eligibilityService->getIssues($certificateRequest->contractor)
            : [];
        $data['delivery'] = $this->deliveryStatus($certificateRequest);

        return $this->success($data);
    }

    /**
     * وصلت الشهادة للمقاول؟ وفتحها؟ — لنافذة عرض الشهادة باللوحة.
     *
     * - notified_at: إشعار "شهادتك جاهزة" انكتب بصندوق إشعارات المقاول بالتطبيق (الإشعار
     *   بيمرّ على الطابور، فبيضل null لثواني بعد الإصدار).
     * - notification_read_at: المقاول فتح الإشعار.
     * - has_device: عنده توكن FCM، يعني انبعت push لجواله. بدونه الإشعار بالتطبيق بس.
     * - viewed_at / views_count: فتح ملف الشهادة نفسه من الرابط المتتبَّع.
     */
    private function deliveryStatus(CertificateRequest $certificateRequest): ?array
    {
        if ($certificateRequest->status !== 'issued' || ! $certificateRequest->contractor) {
            return null;
        }

        $notification = $certificateRequest->contractor->notifications()
            ->where('type', CertificateRequestStatusNotification::class)
            ->where('data->request_id', $certificateRequest->id)
            ->where('data->status', 'issued')
            ->when($certificateRequest->issued_at, fn ($q, $at) => $q->where('created_at', '>=', $at->copy()->subMinute()))
            ->latest()
            ->first();

        return [
            'notified_at'          => $notification?->created_at,
            'notification_read_at' => $notification?->read_at,
            'has_device'           => filled($certificateRequest->contractor->fcm_token),
            'viewed_at'            => $certificateRequest->viewed_at,
            'last_viewed_at'       => $certificateRequest->last_viewed_at,
            'views_count'          => (int) $certificateRequest->views_count,
        ];
    }

    /** نفس CertificateRequestResource + حالة الوصول — ردود الإصدار بتفتح نافذة العرض مباشرة */
    private function issuedPayload(CertificateRequest $certificateRequest): array
    {
        $fresh = $certificateRequest->fresh(['contractor', 'reviewedBy:id,name', 'pendingPayment']);
        $data  = (new CertificateRequestResource($fresh))->resolve();
        $data['delivery'] = $this->deliveryStatus($fresh);

        return $data;
    }

    /**
     * GET /api/v1/certificates/{certificateRequest}/certificate.pdf (رابط موقَّع)
     *
     * الرابط اللي بيستلمه المقاول بالتطبيق والبريد — كل فتح بيتسجّل، فاللوحة بتعرف إذا
     * المقاول فتح شهادته.
     */
    public function file(Request $request, CertificateRequest $certificateRequest)
    {
        if (! $request->hasValidRelativeSignature()) {
            abort(403, 'رابط الشهادة غير صالح.');
        }

        $path = $certificateRequest->certificate_path;
        if ($certificateRequest->status !== 'issued' || ! $path || ! Storage::disk('public')->exists($path)) {
            abort(404, 'الشهادة غير متاحة.');
        }

        // تحديث مباشر بدون updated_at — الفتح مش تعديل على الطلب
        CertificateRequest::whereKey($certificateRequest->id)->update([
            'viewed_at'      => $certificateRequest->viewed_at ?? now(),
            'last_viewed_at' => now(),
            'views_count'    => \Illuminate\Support\Facades\DB::raw('views_count + 1'),
        ]);

        $serial = 'MC-' . str_pad((string) $certificateRequest->id, 6, '0', STR_PAD_LEFT);

        return Storage::disk('public')->response($path, "{$serial}.pdf", [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * لا موافقة ولا إصدار مقابل مال غير مؤكَّد (TASK-17 #5). الطلب المرتبط بدفعة قيد
     * التأكيد يُرى ويُراجَع باللوحة، لكن الشهادة لا تُصدر حتى يعتمد المحاسب الدفعة.
     */
    private function blockedByUnconfirmedPayment(CertificateRequest $certificateRequest): ?\Illuminate\Http\JsonResponse
    {
        if (! $certificateRequest->loadMissing('pendingPayment')->isAwaitingPaymentConfirmation()) {
            return null;
        }

        return $this->error(
            'دفعة رسوم هذا الطلب لم تُعتمد بعد — يجب تأكيد الدفعة من سجل المدفوعات قبل الموافقة أو الإصدار.',
            422,
            ['pending_payment_id' => $certificateRequest->pending_payment_id],
            'payment_unconfirmed',
        );
    }

    /** POST /api/v1/dashboard/certificate-requests/{certificateRequest}/approve */
    public function approve(Request $request, CertificateRequest $certificateRequest)
    {
        if ($blocked = $this->blockedByUnconfirmedPayment($certificateRequest)) {
            return $blocked;
        }

        // شهادة العضوية: رقم وتاريخ قرار التصنيف (والعنوان) بيندخلوا عند الموافقة،
        // وبينطبعوا لاحقاً بالإصدار التلقائي
        $data = $request->validate([
            'address'         => 'nullable|string|max:255',
            'decision_number' => 'nullable|string|max:100',
            'decision_date'   => 'nullable|date',
        ]);

        $certificateRequest->update([
            'status'        => 'approved',
            'reject_reason' => null,
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => now(),
        ] + ($certificateRequest->type === 'membership' ? $this->certificateFields($data) : []));

        $certificateRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certificateRequest)
        );

        return $this->success(new CertificateRequestResource($certificateRequest->fresh()), 'تمت الموافقة على الطلب.');
    }

    /** POST /api/v1/dashboard/certificate-requests/{certificateRequest}/reject */
    public function reject(RejectCertificateRequestRequest $request, CertificateRequest $certificateRequest)
    {
        $data = $request->validated();

        $certificateRequest->update([
            'status'        => 'rejected',
            'reject_reason' => $data['reject_reason'],
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => now(),
        ]);

        $certificateRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certificateRequest)
        );

        return $this->success(new CertificateRequestResource($certificateRequest->fresh()), 'تم رفض الطلب.');
    }

    /**
     * POST /api/v1/dashboard/certificate-requests/{certificateRequest}/issue
     *
     * بدون ملف: شهادة العضوية بتتولّد تلقائياً (نفس قالب "إصدار مباشر") من بيانات ملف
     * المقاول — ما في داعي يرفع الأدمن PDF. الأنواع التانية ما إلها قالب، فبدها ملف.
     * مع ملف: بيتخزّن كما هو (للأنواع التانية، أو لاستبدال المولَّد بنسخة يدوية).
     */
    public function issue(IssueCertificateRequestRequest $request, CertificateRequest $certificateRequest)
    {
        if ($blocked = $this->blockedByUnconfirmedPayment($certificateRequest)) {
            return $blocked;
        }

        if (! $request->hasFile('certificate') && $certificateRequest->type !== 'membership') {
            return $this->error(
                'الإصدار التلقائي متاح لشهادة العضوية فقط — ارفع ملف PDF للشهادة.',
                422,
                ['certificate' => ['ملف الشهادة مطلوب لهذا النوع.']],
            );
        }

        $oldPath     = $certificateRequest->certificate_path;
        $oldIssuedAt = $certificateRequest->issued_at;
        $generated   = ! $request->hasFile('certificate');

        // بيانات نافذة الإصدار بتغلب على المحفوظة من الموافقة، والفارغ بيرجع لملف المقاول
        $fields = [];
        if ($generated) {
            $fields = $this->certificateFields($request->validated());
            $certificateRequest->fill($fields);
        }

        $path = $generated
            ? $this->pdfService->generate($certificateRequest, $certificateRequest->certificateOverrides())
            : $request->file('certificate')->store('certificates/membership', 'public');

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        $certificateRequest->update([
            'status'           => 'issued',
            'certificate_path' => $path,
            'issued_at'        => now(),
            'reviewed_by'      => Auth::id(),
            'reviewed_at'      => now(),
            // ملف جديد وإشعار جديد — "فتحها المقاول" بيرجع يتحسب من الصفر
            'viewed_at'        => null,
            'last_viewed_at'   => null,
            'views_count'      => 0,
        ] + $fields);

        $certificateRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certificateRequest)
        );

        // بيانات مطبوعة تخالف ملف المقاول — سجل المحددات الهامة، زي الإصدار المباشر
        [$diffBefore, $diffAfter] = $generated
            ? $this->certificateOverrideDiff($certificateRequest->contractor, $certificateRequest->certificateOverrides())
            : [[], []];

        if ($oldPath || $diffAfter) {
            // سجل المحددات الهامة — استبدال ملف شهادة كانت صادرة مسبقاً، و/أو بيانات مخالفة لملف المقاول
            $fileBefore = $oldPath ? ['issued_at' => $oldIssuedAt?->toDateTimeString(), 'certificate_file' => basename($oldPath)] : [];
            $fileAfter  = $oldPath ? ['issued_at' => $certificateRequest->issued_at?->toDateTimeString(), 'certificate_file' => basename($path)] : [];
            AuditLogService::recordCritical(
                Auth::user(),
                'certificate.issued',
                $certificateRequest,
                before: $diffBefore + $fileBefore,
                after: $diffAfter + $fileAfter,
                reason: $request->validated('reason'),
                context: $this->certificateLogContext($certificateRequest),
            );
        } else {
            AuditLogService::record(Auth::user(), 'certificate.issued', $certificateRequest, [
                'contractor_id' => $certificateRequest->contractor_id,
                'generated'     => $generated,
            ]);
        }

        return $this->success(
            $this->issuedPayload($certificateRequest),
            $generated ? 'تم توليد الشهادة وإرسالها للمقاول.' : 'تم إصدار الشهادة بنجاح.',
        );
    }

    /** DELETE /api/v1/dashboard/certificate-requests/{certificateRequest} */
    public function destroy(CertificateRequest $certificateRequest)
    {
        if ($certificateRequest->certificate_path) {
            Storage::disk('public')->delete($certificateRequest->certificate_path);
        }

        AuditLogService::record(Auth::user(), 'certificate.deleted', $certificateRequest, ['contractor_id' => $certificateRequest->contractor_id]);

        $certificateRequest->delete();

        return $this->success(message: 'تم حذف الطلب.');
    }

    /** POST /api/v1/dashboard/certificate-requests/bulk-delete */
    public function bulkDestroy(BulkDestroyCertificateRequestsRequest $request)
    {
        $data = $request->validated();

        $requests = CertificateRequest::whereIn('id', $data['ids'])->get();

        foreach ($requests as $r) {
            if ($r->certificate_path) {
                Storage::disk('public')->delete($r->certificate_path);
            }
            $r->delete();
        }

        AuditLogService::record(
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

    /** POST /api/v1/dashboard/certificate-requests/{certificateRequest}/regenerate */
    public function regenerate(RegenerateCertificateRequestRequest $request, CertificateRequest $certificateRequest)
    {
        if ($certificateRequest->type !== 'membership') {
            return $this->error('إعادة الإصدار متاحة لشهادات العضوية فقط.', 422);
        }

        $data = $request->validated();
        $oldPath = $certificateRequest->certificate_path;
        $oldIssuedAt = $certificateRequest->issued_at;

        // القيم المُدخلة الآن بتغلب على المحفوظة على الطلب (من الموافقة/الإصدار)، وبتنحفظ بداله
        $certificateRequest->fill($this->certificateFields($data));
        $path = $this->pdfService->generate($certificateRequest, $certificateRequest->certificateOverrides());

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

        // سجل المحددات الهامة — إعادة إصدار شهادة عضوية (مع أي بيانات مخالفة لملف المقاول)
        [$before, $after] = $this->certificateOverrideDiff($certificateRequest->contractor, $data);
        AuditLogService::recordCritical(
            Auth::user(),
            'certificate.regenerated',
            $certificateRequest,
            before: $before + ['issued_at' => $oldIssuedAt?->toDateTimeString()],
            after: $after + ['issued_at' => $certificateRequest->issued_at?->toDateTimeString()],
            reason: $data['reason'] ?? null,
            context: $this->certificateLogContext($certificateRequest),
        );

        return $this->success($this->issuedPayload($certificateRequest), 'تمت إعادة إصدار الشهادة بنجاح.');
    }

    /** POST /api/v1/dashboard/certificate-requests/issue-membership */
    public function adminIssueMembership(AdminIssueMembershipCertificateRequest $request)
    {
        $data = $request->validated();
        $contractor = Contractor::findOrFail($data['contractor_id']);

        $certRequest = $contractor->certificateRequests()->create([
            'type'   => 'membership',
            'notes'  => $data['notes'] ?? 'إصدار مباشر من لوحة التحكم',
            'status' => 'pending',
        ] + $this->certificateFields($data));

        try {
            $path = $this->pdfService->generate($certRequest, $certRequest->certificateOverrides());
        } catch (\Throwable $e) {
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

        $certRequest->contractor?->notify(
            new CertificateRequestStatusNotification($certRequest)
        );

        [$before, $after] = $this->certificateOverrideDiff($contractor, $data);
        if ($after) {
            // سجل المحددات الهامة — شهادة صدرت ببيانات تختلف عن المسجّلة بملف المقاول
            AuditLogService::recordCritical(
                Auth::user(),
                'certificate.admin_issued_membership',
                $certRequest,
                before: $before,
                after: $after,
                reason: $data['notes'] ?? null,
                context: $this->certificateLogContext($certRequest),
            );
        } else {
            AuditLogService::record(
                Auth::user(),
                'certificate.admin_issued_membership',
                $certRequest,
                ['contractor_id' => $contractor->id, 'contractor_name' => $contractor->name]
            );
        }

        return $this->success(
            $this->issuedPayload($certRequest),
            'تم إصدار شهادة العضوية بنجاح.',
            201,
        );
    }

    /**
     * حقول الشهادة المُرسلة (address/decision_number/decision_date) بأسماء أعمدة الطلب.
     * الفارغ ما بيمسح المحفوظ — بيضل اللي انحفظ عند الموافقة أو إصدار سابق.
     */
    private function certificateFields(array $data): array
    {
        return array_filter([
            'certificate_address' => $data['address'] ?? null,
            'decision_number'     => $data['decision_number'] ?? null,
            'decision_date'       => $data['decision_date'] ?? null,
        ], fn ($v) => filled($v));
    }

    /**
     * يقارن البيانات المُدخلة يدوياً عند إصدار شهادة (العنوان/رقم وتاريخ قرار التصنيف)
     * مع المسجّلة بملف المقاول، ويرجّع [قبل, بعد] للحقول المختلفة فقط.
     *
     * @return array{0: array<string, ?string>, 1: array<string, string>}
     */
    private function certificateOverrideDiff(?Contractor $contractor, array $data): array
    {
        if (! $contractor) {
            return [[], []];
        }

        $record = MembershipCertificatePdfService::recordFields($contractor);

        // العنوان بيعتبر مطابق إذا طابق أي حقل عنوان بملف المقاول (المدينة/المحافظة/العنوان) —
        // شاشة الإصدار بتعبّيه تلقائياً من حقل غير اللي بيستخدمه التوليد أحياناً.
        $accepted = [
            'address'         => [$record['address'], $contractor->city, $contractor->governorate?->name, $contractor->address],
            'decision_number' => [$record['decision_number']],
            'decision_date'   => [$record['decision_date']],
        ];

        $before = $after = [];
        foreach ($accepted as $field => $values) {
            $value = $data[$field] ?? null;
            if (! filled($value)) {
                continue;
            }
            if ($field === 'decision_date') {
                $value = \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
            }

            $matches = collect($values)->contains(fn ($v) => filled($v) && AuditLogService::sameValue($v, $value));
            if (! $matches) {
                $before[$field] = $record[$field];
                $after[$field]  = $value;
            }
        }

        return [$before, $after];
    }

    private function certificateLogContext(CertificateRequest $certificateRequest): array
    {
        $contractor = $certificateRequest->contractor;

        return [
            'contractor_id'     => $certificateRequest->contractor_id,
            'contractor_name'   => $contractor?->name,
            'membership_number' => $contractor?->membership_number,
        ];
    }

    /** GET /api/v1/certificates/verify/{token} */
    public function verify(string $token)
    {
        try {
            $payload = json_decode(Crypt::decryptString(urldecode($token)), true);
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
