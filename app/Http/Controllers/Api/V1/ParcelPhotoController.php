<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Portal\StoreParcelPhotosRequest;
use App\Models\ParcelPhoto;
use App\Services\Owner\OwnerPhotoUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/** The mobile app's way for an owner to add photos of their own parcel, reviewed by staff before anyone sees them. */
class ParcelPhotoController extends ApiController
{
    public function store(StoreParcelPhotosRequest $request, int $parcel, OwnerPhotoUploader $uploader): JsonResponse
    {
        $found = $this->parcels()->base()->find($parcel);

        if ($found === null) {
            return $this->respondError(__('api.parcel_not_owned'), Response::HTTP_NOT_FOUND);
        }

        /** @var list<UploadedFile> $files */
        $files = array_values((array) $request->file('photos'));
        $photos = $uploader->store($this->owner(), $found, $files, $request->photoType());

        return $this->respondCreated([
            'photos' => array_map(fn (ParcelPhoto $p): array => ['id' => $p->id, 'status' => 'pending', 'original_name' => $p->original_name], $photos),
            'message' => __('portal.photos_uploaded', ['count' => count($photos)]),
        ]);
    }
}
