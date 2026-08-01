<?php

declare(strict_types=1);

namespace App\Services\Owner;

use App\Enums\ModificationRequestStatus;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\Parcel;

/**
 * Creates an owner's request to change a parcel field.
 *
 * Synced tables are read-only on our side, so a change is recorded as a request
 * for review rather than written straight onto the parcel.
 */
class ModificationRequestCreator
{
    /** Fields that live on the deed rather than the parcel. */
    private const DEED_FIELDS = ['deed_status', 'deed_class'];

    public function create(Owner $owner, Parcel $parcel, string $fieldName, string $newValue, ?string $notes): ModificationRequest
    {
        return ModificationRequest::create([
            'parcel_id' => $parcel->id,
            'requested_by' => $owner->getKey(),
            'field_name' => $fieldName,
            // Snapshot the value at request time, for the reviewer's context.
            'old_value' => $this->currentValue($parcel, $fieldName),
            'new_value' => $newValue,
            'status' => ModificationRequestStatus::Pending->value,
            'notes' => $notes,
        ]);
    }

    private function currentValue(Parcel $parcel, string $fieldName): ?string
    {
        $source = in_array($fieldName, self::DEED_FIELDS, true)
            ? $parcel->latestDeed
            : $parcel;

        $value = $source?->{$fieldName};

        return $value === null ? null : (string) $value;
    }
}
