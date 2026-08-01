<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ParcelPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ParcelPhoto
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->photo_type?->value,
            'file_type' => $this->fileType(),
            'download_url' => route('api.documents.download', $this->id),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Extension taken from the stored path, so the app can pick a viewer. */
    private function fileType(): ?string
    {
        $extension = pathinfo((string) $this->photo_url, PATHINFO_EXTENSION);

        return $extension === '' ? null : strtolower($extension);
    }
}
