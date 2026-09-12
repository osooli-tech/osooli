<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single global row holding the client's colour overrides for the
 * dashboard map. Only overridden keys are persisted; everything else falls
 * back to DEFAULTS, so a fresh install needs no seeding to look right.
 *
 * There is only ever one row, so it is looked up by "the first one that
 * exists" rather than a fixed id — updateOrCreate(['id' => 1], ...) looks
 * tempting here but silently fails to pin id 1 on creation ('id' isn't
 * fillable, so firstOrNew's mass-assignment drops it and the row lands on
 * whatever the id sequence gives next).
 *
 * @property int $id
 * @property array<string, mixed>|null $overrides
 */
class MapAppearanceSetting extends Model
{
    protected $fillable = ['overrides'];

    protected $casts = [
        'overrides' => 'array',
    ];

    /** @var array<string, mixed> */
    public const DEFAULTS = [
        'parcels_fill' => '#00b386',
        'parcels_outline' => '#39ff14',
        'projects_fill' => '#c9a84c',
        'buildings_fill' => '#4a90d9',
        'colour_modes' => [
            'deed_status' => [
                'محدث' => '#00b386',
                'قديم' => '#d9534f',
            ],
            'asset_type' => [
                'أرض' => '#00b386',
                'فيلا' => '#c9a84c',
                'عمارة' => '#4a90d9',
                'شقة' => '#9b6dd6',
                'مستودع' => '#e07b39',
            ],
            // Matched against a Mapbox `to-string` of the boolean is_priced
            // property, which yields these exact literal strings — not '1'/'0'.
            'priced' => [
                'true' => '#00b386',
                'false' => '#8a8f98',
            ],
            'fall_in' => [
                'مخطط زراعي' => '#00b386',
                'مخطط بلدية' => '#d9534f',
                'طلبات احكام' => '#c9a84c',
                'حجة استحكام' => '#4a90d9',
                'مخطط' => '#9b6dd6',
            ],
        ],
    ];

    /** @return array<string, mixed> */
    public static function current(): array
    {
        $setting = static::query()->first();
        $overrides = $setting instanceof self ? ($setting->overrides ?? []) : [];

        return array_replace_recursive(self::DEFAULTS, $overrides);
    }

    /** @param array<string, mixed> $overrides */
    public static function replaceOverrides(array $overrides): void
    {
        $setting = static::query()->first() ?? new self;
        $setting->overrides = $overrides;
        $setting->save();
    }
}
