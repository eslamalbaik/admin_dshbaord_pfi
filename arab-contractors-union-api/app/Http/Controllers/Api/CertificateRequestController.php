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

        return $this->success([
            'contractor' => [
                'name'              => $contractor->name,
                'membership_number' => $contractor->membership_number,
                'status'            => $contractor->status,
                'is_frozen'         => (bool) $contractor->is_frozen,
            ],
            'can_request'        => count($issues) === 0,
            'requirement_issues' => $issues,
            'requests'           => $contractor->certificateRequests()
                ->latest()
                ->get()
                ->map(fn ($r) => $this->format($r))
                ->values(),
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

        $issues = $this->requirementIssues($contractor);
        if (count($issues) > 0) {
            return $this->error(
                'لا يمكن تقديم الطلب قبل تسوية المتطلبات المستحقّة.',
                403,
                null,
                'requirements_pending',
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

        // إشعار الإدارة بوجود طلب جديد
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
}
