<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvanceActivityApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'advance_request_activity_id' => $this->advance_request_activity_id ?? '',
            'submission_no' => $this->submission_no ?? '',
            'approval_level' => $this->approval_level ?? '',
            'approver_id' => $this->approver_id ?? '',
            'approver_status' => $this->approver_status ?? '',
            'approved_amount' => $this->approved_amount ?? '',
            'approval_remarks' => $this->approval_remarks ?? '',
            'approved_at' => $this->approved_at ?? '',
            'created_at' => $this->created_at ?? '',
            'updated_at' => $this->updated_at ?? '',
        ];
    }
}