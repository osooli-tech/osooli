<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Deed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deed
 */
class DeedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'deed_no' => $this->deed_no,
            // Hijri date is stored as plain text and must not be treated as a Gregorian date.
            'deed_date_hijri' => $this->deed_date_hijri,
            'deed_area_sqm' => $this->deed_area === null ? null : (float) $this->deed_area,
            // Stored as plain enum-valued text, not cast to a PHP enum.
            'deed_status' => $this->deed_status,
            'deed_class' => $this->deed_class,
        ];
    }
}
