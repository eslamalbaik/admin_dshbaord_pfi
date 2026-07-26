<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ContractorNameChangeRequest;
use App\Models\User;
use App\Notifications\NameChangeRequestStatusNotification;
use App\Notifications\NameChangeRequestSubmittedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ContractorNameChangeRequestController extends Controller
{
    use ApiResponseTrait;

    /** تنسيق طلب تعديل الاسم لاستجابة الـ API */
    private function format(ContractorNameChangeRequest $r): array
    {
        return [
            'id'                   => $r->id,
            'contractor_id'        => $r->contractor_id,
            'contractor'           => $r->contractor?->name,
            'membership_number'    => $r->contractor?->membership_number,
            'current_name'         => $r->current_name,
            'requested_name'       => $r->requested_name,
            'supporting_document_url' => $r->supporting_document
                ? Storage::disk('public')->url($r->supporting_document)
                : null,
            'status'               => $r->status,
            'status_label'         => $r->status_label,
            'reject_reason'        => $r->reject_reason,
            'reviewed_by'          => $r->reviewer?->name,
            'reviewed_at'          => $r->reviewed_at,
            'created_at'           => $r->created_at,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/v1/contractor/auth/name-change-request
     * آخر طلب تعديل اسم قدّمه المقاول (لعرض حالته ومنع التكرار في الواجهة).
     */
    public function show(Request $request)
    {
        $contractor = $request->user();
        $latest = $contractor->nameChangeRequests()->latest()->first();

        return $this->success($latest ? $this->format($latest) : null);
    }

    /**
     * POST /api/v1/contractor/auth/name-change-request
     * يقدّم المقاول طلب تعديل اسم الشركة مرفقاً بكتاب رسمي يثبت التغيير.
     */
    public function store(Request $request)
    {
        $contractor = $request->user();

        $data = $request->validate([
            'requested_name'       => 'required|string|max:255',
            'supporting_document'  => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $hasPending = $contractor->nameChangeRequests()->where('status', 'pending')->exists();
        if ($hasPending) {
            return $this->error('لديك طلب تعديل اسم سابق قيد المراجعة، يرجى انتظار الرد عليه.', 422);
        }

        $documentPath = $request->file('supporting_document')->store('contractors/name-change', 'public');

        $nameChangeRequest = $contractor->nameChangeRequests()->create([
            'current_name'         => $contractor->name,
            'requested_name'       => $data['requested_name'],
            'supporting_document'  => $documentPath,
            'status'               => 'pending',
        ]);

        $admins = User::where('role', 'admin')->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NameChangeRequestSubmittedNotification($nameChangeRequest));
        }

        return $this->success(
            $this->format($nameChangeRequest),
            'تم تقديم طلب تعديل اسم الشركة بنجاح، سيتم إشعارك عند مراجعته.',
            201,
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin Dashboard
    // ═════════════════════════════════════════════════════════════════════════

    /** GET /api/v1/dashboard/name-change-requests */
    public function index(Request $request)
    {
        $query = ContractorNameChangeRequest::with(['contractor', 'reviewer']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->latest()->paginate(15);
        $requests->getCollection()->transform(fn ($r) => $this->format($r));

        return $this->paginated($requests);
    }

    /** POST /api/v1/dashboard/name-change-requests/{nameChangeRequest}/approve */
    public function approve(ContractorNameChangeRequest $nameChangeRequest)
    {
        if ($nameChangeRequest->status !== 'pending') {
            return $this->error('تم البتّ بهذا الطلب مسبقاً.', 422);
        }

        $nameChangeRequest->update([
            'status'      => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $nameChangeRequest->contractor?->update(['name' => $nameChangeRequest->requested_name]);
        $nameChangeRequest->contractor?->notify(new NameChangeRequestStatusNotification($nameChangeRequest));

        return $this->success(message: 'تمت الموافقة على تعديل اسم الشركة.');
    }

    /** POST /api/v1/dashboard/name-change-requests/{nameChangeRequest}/reject */
    public function reject(Request $request, ContractorNameChangeRequest $nameChangeRequest)
    {
        if ($nameChangeRequest->status !== 'pending') {
            return $this->error('تم البتّ بهذا الطلب مسبقاً.', 422);
        }

        $data = $request->validate(['reject_reason' => 'nullable|string|max:500']);

        $nameChangeRequest->update([
            'status'        => 'rejected',
            'reject_reason' => $data['reject_reason'] ?? null,
            'reviewed_by'   => Auth::id(),
            'reviewed_at'   => now(),
        ]);

        $nameChangeRequest->contractor?->notify(new NameChangeRequestStatusNotification($nameChangeRequest));

        return $this->success(message: 'تم رفض طلب تعديل اسم الشركة.');
    }
}
