<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Owner
 */
class OwnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'national_id' => $this->national_id,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
