<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $forContractor = $request->user() instanceof \App\Models\Contractor;

        return [
            'id'                => $this->id,
            'contractor_id'     => $this->contractor_id,
            'contractor'        => $this->contractor?->name,
            'membership_number' => $this->contractor?->membership_number,
            'type'              => $this->type,
            'type_label'        => $this->type_label,
            'status'            => $this->status,
            'status_label'      => $this->status_label,
            // الطلب قُدِّم بانتظار اعتماد دفعة الرسوم (TASK-17 #5) — اللوحة تحتاج معرفة ذلك
            // لتوضّح للأدمن لماذا الموافقة والإصدار موقوفان، بدل أن يكتشفه عبر 422.
            'awaiting_payment_confirmation' => $this->isAwaitingPaymentConfirmation(),
            'pending_payment'   => $this->pendingPayment ? [
                'id'                 => $this->pendingPayment->id,
                'transaction_number' => $this->pendingPayment->transaction_number,
                'amount'             => $this->pendingPayment->amount,
                'currency'           => $this->pendingPayment->currency,
                'status'             => $this->pendingPayment->status,
                'submitted_at'       => $this->pendingPayment->submitted_at,
            ] : null,
            'notes'             => $this->notes,
            'attachment_url'    => $this->attachment_url,
            'reject_reason'     => $this->reject_reason,
            // المقاول بياخد الرابط المتتبَّع (فتحه بيتسجّل)، واللوحة الرابط المباشر
            'certificate_url'   => $forContractor ? $this->tracked_certificate_url : $this->certificate_url,
            'request_date'      => $this->created_at,
            'issue_date'        => $this->issued_at,
            'reviewed_by'       => $this->reviewedBy?->name,
            $this->mergeUnless($forContractor, [
                'viewed_at'      => $this->viewed_at,
                'last_viewed_at' => $this->last_viewed_at,
                'views_count'    => (int) $this->views_count,
            ]),
        ];
    }
}
