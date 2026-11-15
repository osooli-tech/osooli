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
    /**
     * The photos the owner added to this parcel that nobody sees yet: still
     * under review, or turned down and why. Approved ones are documents now.
     */
    public function mine(int $parcel): JsonResponse
    {
        $found = $this->parcels()->base()->find($parcel);

        if ($found === null) {
            return $this->respondError(__('api.parcel_not_owned'), Response::HTTP_NOT_FOUND);
        }

        $photos = ParcelPhoto::query()->forReview()
            ->where('parcel_id', $found->id)
            ->where('uploaded_by_owner', $this->owner()->getKey())
            ->where('status', '!=', ParcelPhoto::STATUS_APPROVED)
            ->latest('id')
            ->get()
            ->map(fn (ParcelPhoto $p): array => [
                'id' => $p->id,
                'status' => $p->status === ParcelPhoto::STATUS_REJECTED ? 'rejected' : 'pending',
                'original_name' => $p->original_name,
                'rejection_reason' => $p->status === ParcelPhoto::STATUS_REJECTED ? $p->rejection_reason : null,
                'uploaded_at' => $p->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return $this->respondCollection($photos);
    }

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
