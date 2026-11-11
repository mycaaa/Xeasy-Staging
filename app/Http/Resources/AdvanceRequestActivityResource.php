<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvanceRequestActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'advance_request_employee_id' => $this->advance_request_employee_id ?? '',
            'activity_type_id' => $this->activity_type_id ?? '',
            'activity_crops_id' => $this->activity_crops_id ?? '',
            'activity_variety_id' => $this->activity_variety_id ?? '',
            'amount' => $this->amount ?? '0.00',
            'purpose' => $this->purpose ?? '',
            'current_approval_level' => $this->current_approval_level ?? 0,
            'approval_status' => $this->approval_status ?? '',
            'finance_id' => $this->finance_id ?? '',
            'finance_approved_amount' => $this->finance_approved_amount ?? '',
            'finance_status' => $this->finance_status ?? '',
            'finance_remark' => $this->finance_remark ?? '',
            'finance_date' => $this->finance_date ?? '',
            'created_at' => $this->created_at ?? '',
            'updated_at' => $this->updated_at ?? '',
            'deleted_at' => $this->deleted_at ?? '',
            'approvals' => AdvanceActivityApprovalResource::collection($this->whenLoaded('approvals')),
        ];
    }
}