<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Owner-portal settings an administrator can change. One row; anything not
 * set falls back to DEFAULTS.
 *
 * `linked_parcels` is what a parent owner may see of a parcel held under
 * them (parcels.parent_owner_id) whose deed is in someone else's name. The
 * parcel itself — where it is, its size, who holds it — is always shown;
 * these switches add the rest. All on by default: a parent owner sees such
 * a parcel as they would their own, unless an administrator narrows it.
 *
 * @property array<string, bool>|null $linked_parcels
 */
class PortalSetting extends Model
{
    /** The estimated value and price per square metre. */
    public const SHOW_VALUE = 'show_value';

    /** The holder's deed number, date and status. */
    public const SHOW_DEED = 'show_deed';

    /** Viewing and downloading the parcel's documents, the holder's deed scan included. */
    public const SHOW_DOCUMENTS = 'show_documents';

    /** @var array<string, bool> */
    public const DEFAULTS = [
        self::SHOW_VALUE => true,
        self::SHOW_DEED => true,
        self::SHOW_DOCUMENTS => true,
    ];

    protected $fillable = ['linked_parcels'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['linked_parcels' => 'array'];
    }

    /** @return array<string, bool> */
    public static function linkedParcels(): array
    {
        $row = static::query()->first();
        $stored = $row instanceof self ? ($row->linked_parcels ?? []) : [];

        return array_map('boolval', array_intersect_key($stored, self::DEFAULTS)) + self::DEFAULTS;
    }

    public static function allows(string $switch): bool
    {
        return self::linkedParcels()[$switch] ?? false;
    }
}
