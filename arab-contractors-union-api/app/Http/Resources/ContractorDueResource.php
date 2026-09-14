<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractorDueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'contractor_id'     => $this->contractor_id,
            'contractor'        => $this->contractor?->name,
            'membership_number' => $this->contractor?->membership_number,
            'year'              => $this->year,
            'period'            => $this->period,
            'reference_number'  => $this->reference_number,
            'description'       => $this->description,
            'amount_jod'        => $this->amount_jod,
            'paid_jod'          => $this->paid_jod,
            'remaining_jod'     => $this->remaining_jod,
            'status'            => $this->status,
            'status_label'      => $this->status_label,
            'source'            => $this->source,
            'due_date'          => $this->due_date?->toDateString(),
            'notes'             => $this->notes,
            'created_by'        => $this->createdBy?->name,
            'created_at'        => $this->created_at,
            'discount_type'        => $this->discount_type,
            'discount_value'       => $this->discount_value,
            'discount_amount_jod'  => $this->discount_amount_jod,
            'discount_reason'      => $this->discount_reason,
            'original_amount_jod'  => $this->original_amount_jod,
            'fee_breakdown'        => $this->fee_breakdown,
        ];
    }
}
