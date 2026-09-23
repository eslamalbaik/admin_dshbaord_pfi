<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\OtpCooldownException;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\ProfileUpdateRequestStatusNotification;
use App\Notifications\ProfileUpdateRequestSubmittedNotification;
use App\Services\AuditLogService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * طلبات تعديل بيانات البروفايل الثانوية (REQ-26) — لا كتابة مباشرة على contractors
 * إلا بعد موافقة الإدارة. تغيير الجوال هنا مسموح أيضاً (مساره الأصلي الفوري بدون موافقة
 * يبقى قائماً في ContractorAuthController) لكنه يتطلب تحقق OTP مسبق عبر send-phone-otp
 * (نفس OtpService المستخدم بمسار ContractorAuthController، بنفس الـ purpose "profile_phone").
 */
class ProfileUpdateRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private OtpService $otpService) {}

    private function format(ProfileUpdateRequest $r): array
    {
        $contractor = $r->contractor;

        return [
            'id'                => $r->id,
            'contractor_id'     => $r->contractor_id,
            'contractor'        => $contractor?->name,
            'membership_number' => $contractor?->membership_number,
            // القيم الحالية للحقول المطلوب تعديلها فقط — تُمكّن لوحة الأدمن من عرض
            // "الحالي ← المقترح" بدل قائمة قيم مقترحة بلا سياق يُبتّ بها بالموافقة.
            'current_data'      => $this->currentDataFor($r, $contractor),
            'proposed_data'     => $r->proposed_data,
            'attachment_url'    => $r->attachment_url,
            'status'            => $r->status,
            'status_label'      => $r->status_label,
            'reject_reason'     => $r->reject_reason,
            'reviewed_by'       => $r->reviewer?->name,
            'reviewed_at'       => $r->reviewed_at,
            'created_at'        => $r->created_at,
        ];
    }

    /**
     * القيم الحالية المقابلة لمفاتيح proposed_data. بعد الموافقة تكون قيمة العقد قد
     * طُبّقت فعلاً على contractors، فتتطابق الحالية مع المقترحة — وهذا مقصود: الفرق
     * يهمّ فقط أثناء المراجعة (pending).
     */
    private function currentDataFor(ProfileUpdateRequest $r, ?Contractor $contractor): array
    {
        if (! $contractor) {
            return [];
        }

        $keys = array_intersect(array_keys($r->proposed_data ?? []), ProfileUpdateRequest::ALLOWED_FIELDS);

        return collect($keys)
            ->mapWithKeys(fn ($key) => [$key => $contractor->{$key}])
            ->all();
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
     * العضوية/رقم المشتغل مقفلة تماماً ولا تُقبل هنا إطلاقاً. تغيير الجوال يتطلب otp
     * صحيح تم تحقيقه مسبقاً عبر send-phone-otp (نفس رقم الجوال المُرسَل هنا).
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
            'phone'                   => 'sometimes|string|max:20|unique:contractors,phone,' . $contractor->id,
            'otp'                     => 'required_with:phone|string|size:6',
            'attachment'              => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $phoneOtpVerifiedAt = null;
        if (array_key_exists('phone', $data)) {
            try {
                $this->otpService->verifyOtp('profile_phone', $contractor->id, $data['otp']);
            } catch (ValidationException $e) {
                return $this->error('رمز التحقق غير صحيح أو انتهت صلاحيته.', 422, null, 'invalid_otp');
            }
            $phoneOtpVerifiedAt = now();
        }

        // whitelist صريح — استبعاد أي حقل خارج ALLOWED_FIELDS حتى لو مرّ التحقق أعلاه بالخطأ مستقبلاً
        $proposedData = array_intersect_key($data, array_flip(ProfileUpdateRequest::ALLOWED_FIELDS));

        if (empty($proposedData)) {
            return $this->error('لم يتم تحديد أي بيانات للتعديل.', 422);
        }

        $attachmentPath = $request->file('attachment')->store('contractors/profile-update', 'public');

        $profileRequest = $contractor->profileUpdateRequests()->create([
            'proposed_data'         => $proposedData,
            'attachment'            => $attachmentPath,
            'status'                => 'pending',
            'phone_otp_verified_at' => $phoneOtpVerifiedAt,
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

    /**
     * POST /api/v1/contractor/profile-update-requests/send-phone-otp
     * الخطوة 1 قبل تضمين phone بطلب تعديل بيانات — يرسل رمز تحقق يُستخدم لاحقاً في store().
     */
    public function sendPhoneOtp(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:20']);
        $contractor = $request->user();

        try {
            $result = $this->otpService->sendOtp(
                'profile_phone',
                $contractor->id,
                $data['phone'],
                'اتحاد المقاولين: رمز تحقق تعديل رقم الجوال: {otp}. صالح لمدة 10 دقائق.',
            );
        } catch (OtpCooldownException $e) {
            return $this->error($e->getMessage(), 429, ['expires_in' => $e->expiresIn], 'otp_cooldown');
        }

        $responseData = ['expires_in' => $result['expires_in']];
        if ($result['is_preview']) {
            $responseData['otp_preview'] = $result['otp'];
        }

        return $this->success($responseData, 'تم إرسال رمز التحقق إلى رقم الجوال.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/dashboard/profile-update-requests */
    public function index(Request $request)
    {
        // لا بدّ من تحميل أعمدة ALLOWED_FIELDS أيضاً — format() يبني منها current_data،
        // وقصر الـ select على id,name,membership_number كان يُرجعها null دائماً.
        $query = ProfileUpdateRequest::with([
            'contractor:id,name,membership_number,' . implode(',', ProfileUpdateRequest::ALLOWED_FIELDS),
            'reviewer:id,name',
        ]);

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
