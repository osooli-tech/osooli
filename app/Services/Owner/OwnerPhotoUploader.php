<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Enums\PhotoType;
use App\Models\AuditLog;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Support\DocumentVault;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Photos an owner adds of their own parcel, from the portal or the app. They
 * are stored and sealed like any document and wait in the staff review queue;
 * nobody sees one, the owner included, until a reviewer approves it. The
 * caller has already checked that the owner holds the parcel.
 */
class OwnerPhotoUploader
{
    /**
     * @param  list<UploadedFile>  $files
     * @return list<ParcelPhoto>
     */
    public function store(Owner $owner, Parcel $parcel, array $files, PhotoType $type): array
    {
        $stored = [];

        foreach ($files as $file) {
            // A generated name, never the owner's: theirs may collide or carry path separators.
            $path = $file->store(now()->format('Y/m'), ParcelPhoto::PRIVATE_DISK);

            if (! is_string($path) || $path === '') {
                throw new RuntimeException('An owner photo could not be stored.');
            }
            DocumentVault::sealOnDisk(ParcelPhoto::PRIVATE_DISK, $path);

            $stored[] = DB::transaction(function () use ($owner, $parcel, $file, $path, $type): ParcelPhoto {
                $photo = ParcelPhoto::create([
                    'parcel_id' => $parcel->getKey(),
                    'photo_url' => $path,
                    'photo_type' => $type,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'storage_disk' => ParcelPhoto::PRIVATE_DISK,
                    'status' => ParcelPhoto::STATUS_PENDING,
                    'uploaded_by_owner' => $owner->getKey(),
                ]);

                AuditLog::create([
                    'owner_id' => $owner->getKey(),
                    'action' => 'owner_photo_upload',
                    'target_type' => 'document',
                    'target_id' => $photo->id,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $photo;
            });
        }

        return $stored;
    }
}
