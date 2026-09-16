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
                'membership_valid_until' => $activeMembership?->expires_at?->toDateString(),
                'is_expired'        => $activeMembership ? $activeMembership->expires_at?->isPast() : true,
                'latest_request'    => $latestMembershipCert ? new CertificateRequestResource($latestMembershipCert) : null,
            ],
            'classification' => [
                'has_certificate' => (bool) $latestClassificationCert?->certificate_path,
                'certificate_url' => $latestClassificationCert?->certificate_url,
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
        ]);

        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new CertificateRequestSubmittedNotification($certRequest));
        }

        return $this->success(
            new CertificateRequestResource($certRequest),
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

        $paginator = $query->latest()->paginate(15)->through(fn ($r) => new CertificateRequestResource($r));

        return $this->paginated($paginator);
    }

    /** GET /api/v1/dashboard/certificate-requests/{certificateRequest} */
    public function show(CertificateRequest $certificateRequest)
    {
        $certificateRequest->load(['contractor', 'reviewedBy:id,name']);

        $data = (new CertificateRequestResource($certificateRequest))->resolve();
        $data['requirement_issues'] = $certificateRequest->contractor
            ? $this->eligibilityService->getIssues($certificateRequest->contractor)
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

    /** POST /api/v1/dashboard/certificate-requests/{certificateRequest}/issue */
    public function issue(IssueCertificateRequestRequest $request, CertificateRequest $certificateRequest)
    {
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

        return $this->success(new CertificateRequestResource($certificateRequest->fresh()), 'تم إصدار الشهادة بنجاح.');
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

        $path = $this->pdfService->generate($certificateRequest, [
            'address'         => $data['address'] ?? null,
            'decision_number' => $data['decision_number'] ?? null,
            'decision_date'   => $data['decision_date'] ?? null,
        ]);

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

        AuditLogService::record(
            Auth::user(),
            'certificate.regenerated',
            $certificateRequest,
            ['contractor_id' => $certificateRequest->contractor_id]
        );

        return $this->success(new CertificateRequestResource($certificateRequest->fresh()), 'تمت إعادة إصدار الشهادة بنجاح.');
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
        ]);

        try {
            $path = $this->pdfService->generate($certRequest, [
                'address'         => $data['address'] ?? null,
                'decision_number' => $data['decision_number'] ?? null,
                'decision_date'   => $data['decision_date'] ?? null,
            ]);
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

        AuditLogService::record(
            Auth::user(),
            'certificate.admin_issued_membership',
            $certRequest,
            ['contractor_id' => $contractor->id, 'contractor_name' => $contractor->name]
        );

        return $this->success(
            new CertificateRequestResource($certRequest->fresh()),
            'تم إصدار شهادة العضوية بنجاح.',
            201,
        );
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
