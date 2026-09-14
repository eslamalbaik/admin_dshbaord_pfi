<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'contractor_id'     => $this->contractor_id,
            'contractor'        => $this->contractor?->name,
            'membership_number' => $this->contractor?->membership_number,
            'type'              => $this->type,
            'type_label'        => $this->type_label,
            'status'            => $this->status,
            'status_label'      => $this->status_label,
            'notes'             => $this->notes,
            'attachment_url'    => $this->attachment_url,
            'reject_reason'     => $this->reject_reason,
            'certificate_url'   => $this->certificate_url,
            'request_date'      => $this->created_at,
            'issue_date'        => $this->issued_at,
            'reviewed_by'       => $this->reviewedBy?->name,
        ];
    }
}
