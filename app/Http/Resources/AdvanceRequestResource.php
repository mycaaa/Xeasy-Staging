<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvanceRequestResource extends JsonResource
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
            'activity_name_id' => $this->activity_name_id ?? '',
            'month' => $this->month ?? '',
            'req_app_submit_id' => $this->req_app_submit_id ?? '',
            'submission_no' => $this->submission_no ?? '',
            'last_submitted_at' => $this->last_submitted_at ?? '',
            'advance_fill_date' => $this->advance_fill_date ?? '',
            'current_status' => $this->current_status ?? '',
            'reverted_from_status' => $this->reverted_from_status ?? '',
            'revert_reason' => $this->revert_reason ?? '',
            'reverted_by' => $this->reverted_by ?? '',
            'created_by' => $this->created_by ?? '',
            'reverted_at' => $this->reverted_at ?? '',
            'created_at' => $this->created_at ?? '',
            'updated_at' => $this->updated_at ?? '',
            'deleted_at' => $this->deleted_at ?? '',
            'employees' => AdvanceRequestEmployeeResource::collection($this->whenLoaded('employees')),
            'current_approvals' => AdvanceActivityApprovalResource::collection($this->whenLoaded('currentApprovals')),
            'actions' => AdvanceRequestActionResource::collection($this->whenLoaded('actions')),
        ];
    }
}