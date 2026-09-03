<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\ProfileUpdateRequestStatusNotification;
use App\Notifications\ProfileUpdateRequestSubmittedNotification;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * طلبات تعديل بيانات البروفايل الثانوية (REQ-26) — لا كتابة مباشرة على contractors
 * إلا بعد موافقة الإدارة. تغيير الجوال له مسار مستقل فوري (ContractorAuthController)
 * التسجيل الموجود بـContractorRegisterController (Cache + mt_rand، بلا مزوّد SMS فعلي بعد).
 */
class ProfileUpdateRequestController extends Controller
{
    use ApiResponseTrait;

    private function format(ProfileUpdateRequest $r): array
    {
        return [
            'id'             => $r->id,
            'contractor_id'  => $r->contractor_id,
            'contractor'     => $r->contractor?->name,
            'proposed_data'  => $r->proposed_data,
            'attachment_url' => $r->attachment_url,
            'status'         => $r->status,
            'status_label'   => $r->status_label,
            'reject_reason'  => $r->reject_reason,
            'reviewed_by'    => $r->reviewer?->name,
            'reviewed_at'    => $r->reviewed_at,
            'created_at'     => $r->created_at,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/contractor/profile-update-requests/mine */
    public function mine(Request $request)
    {
        $requests = $request->user()->profileUpdateRequests()
            ->latest()
            ->get()
            ->map(fn ($r) => $this->format($r));

        return $this->success($requests->values());
    }

    /**
     * POST /api/v1/contractor/profile-update-requests
     * الحقول المسموح تعديلها فقط (ProfileUpdateRequest::ALLOWED_FIELDS) — الاسم/رقم
     * العضوية/رقم المشتغل مقفلة تماماً ولا تُقبل هنا إطلاقاً.
     */
    public function store(Request $request)
    {
        $contractor = $request->user();

        $hasPending = $contractor->profileUpdateRequests()->where('status', 'pending')->exists();
        if ($hasPending) {
            return $this->error('لديك طلب تعديل بيانات سابق قيد المراجعة، يرجى انتظار الرد عليه.', 422);
        }

        $data = $request->validate([
            'authorized_person'       => 'sometimes|string|max:255',
            'authorized_person_title' => 'sometimes|nullable|string|max:255',
            'email'                   => 'sometimes|nullable|email|max:255',
            'address'                 => 'sometimes|string|max:500',
            'attachment'              => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        // whitelist صريح — استبعاد أي حقل خارج ALLOWED_FIELDS حتى لو مرّ التحقق أعلاه بالخطأ مستقبلاً
        $proposedData = array_intersect_key($data, array_flip(ProfileUpdateRequest::ALLOWED_FIELDS));

        if (empty($proposedData)) {
            return $this->error('لم يتم تحديد أي بيانات للتعديل.', 422);
        }

        $attachmentPath = $request->file('attachment')->store('contractors/profile-update', 'public');

        $profileRequest = $contractor->profileUpdateRequests()->create([
            'proposed_data' => $proposedData,
            'attachment'    => $attachmentPath,
            'status'        => 'pending',
        ]);

        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new ProfileUpdateRequestSubmittedNotification($profileRequest));
        }

        return $this->success(
            $this->format($profileRequest),
            'تم تقديم طلب تعديل البيانات بنجاح، وستبقى بياناتك الحالية سارية لحين المراجعة.',
            201,
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/dashboard/profile-update-requests */
    public function index(Request $request)
    {
        $query = ProfileUpdateRequest::with(['contractor:id,name,membership_number', 'reviewer:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $paginator = $query->latest()->paginate($request->integer('per_page', 15))->through(fn ($r) => $this->format($r));

        return $this->paginated($paginator);
    }

    /** POST /api/v1/dashboard/profile-update-requests/{profileUpdateRequest}/approve */
    public function approve(ProfileUpdateRequest $profileUpdateRequest)
    {
        if ($profileUpdateRequest->status !== 'pending') {
            return $this->error('تم البتّ بهذا الطلب مسبقاً.', 422);
        }

        $profileUpdateRequest->update([
            'status'      => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // تُطبَّق التغييرات فعلياً على contractors فقط الآن — نفس ضمانة ContractorNameChangeRequest
        $profileUpdateRequest->contractor?->update($profileUpdateRequest->proposed_data);

        AuditLogService::record(Auth::user(), 'profile_update_request.approved', $profileUpdateRequest);
        $this->notifyStatusChange($profileUpdateRequest);

        return $this->success(message: 'تمت الموافقة على طلب تعديل البيانات وتحديثها.');
    }

    /** POST /api/v1/dashboard/profile-update-requests/{profileUpdateRequest}/reject */
    public function reject(Request $request, ProfileUpdateRequest $profileUpdateRequest)
    {
        if ($profileUpdateRequest->status !== 'pending') {
            return $this->error('تم البتّ بهذا الطلب مسبقاً.', 422);
        }

        $data = $request->validate(['reject_reason' => 'nullable|string|max:500']);

        $profileUpdateRequest->update([
            'status'        => 'rejected',
            'reject_reason' => $data['reject_reason'] ?? null,
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => now(),
        ]);

        AuditLogService::record(Auth::user(), 'profile_update_request.rejected', $profileUpdateRequest, ['reason' => $data['reject_reason'] ?? null]);
        $this->notifyStatusChange($profileUpdateRequest);

        return $this->success(message: 'تم رفض طلب تعديل البيانات.');
    }

    private function notifyStatusChange(ProfileUpdateRequest $profileUpdateRequest): void
    {
        try {
            $profileUpdateRequest->contractor?->notify(new ProfileUpdateRequestStatusNotification($profileUpdateRequest));
        } catch (\Throwable $e) {
            Log::warning('Failed to send profile-update-request status notification', [
                'request_id' => $profileUpdateRequest->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
