<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvanceRequestEmployeeResource extends JsonResource
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
            'employee_id' => $this->employee_id ?? '',
            'created_at' => $this->created_at ?? '',
            'updated_at' => $this->updated_at ?? '',
            'deleted_at' => $this->deleted_at ?? '',
            'activities' => AdvanceRequestActivityResource::collection($this->whenLoaded('activities')),
        ];
    }
}