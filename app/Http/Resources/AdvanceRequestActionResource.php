<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvanceRequestActionResource extends JsonResource
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
            'advance_request_id' => $this->advance_request_id ?? '',
            'submission_no' => $this->submission_no ?? '',
            'action_type' => $this->action_type ?? '',
            'action_by' => $this->action_by ?? '',
            'action_to' => $this->action_to ?? '',
            'related_employee_id' => $this->related_employee_id ?? '',
            'related_activity_id' => $this->related_activity_id ?? '',
            'related_approval_level' => $this->related_approval_level ?? '',
            'email_template_id' => $this->email_template_id ?? '',
            'remarks' => $this->remarks ?? '',
            'action_at' => $this->action_at ?? '',
            'created_at' => $this->created_at ?? '',
            'updated_at' => $this->updated_at ?? '',
        ];
    }
}