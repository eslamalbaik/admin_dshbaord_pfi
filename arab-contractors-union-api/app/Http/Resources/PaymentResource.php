<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'transaction_number' => $this->transaction_number,
            'contractor'        => $this->contractor?->name,
            'contractor_id'     => $this->contractor_id,
            'membership_id'     => $this->membership_id,
            'equipment_package_id' => $this->equipment_package_id,
            'bank_account_id'   => $this->bank_account_id,
            'amount'            => $this->amount,
            'currency'          => $this->currency ?? 'JOD',
            'exchange_rate'     => $this->exchange_rate,
            'amount_jod'        => $this->amount_jod,
            'rate_source'       => $this->rate_source,
            'type'              => $this->type,
            'status'            => $this->status,
            'status_label'      => $this->status_label,
            'method'            => $this->method,
            'reference_number'  => $this->reference_number,
            'receipt_image_url' => $this->receipt_image_url,
            'receipt_pdf_url'   => $this->receipt_pdf_url,
            'rejection_reason'  => $this->rejection_reason,
            'notes'             => $this->notes,
            'submitted_at'      => $this->submitted_at,
            'confirmed_at'      => $this->confirmed_at,
            'paid_at'           => $this->paid_at,
            'created_at'        => $this->created_at,
        ];
    }
}
