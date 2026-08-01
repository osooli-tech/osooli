<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ModificationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ModificationRequest
 */
class ModificationRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parcel' => $this->parcel === null ? null : [
                'id' => $this->parcel->id,
                'parcel_no' => $this->parcel->parcel_no,
            ],
            'field_name' => $this->field_name,
            'field_label' => $this->fieldLabel(),
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => __('api.modification_statuses.'.$this->status->value),
            'created_at' => $this->created_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
