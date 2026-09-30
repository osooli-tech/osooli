<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AssetType;
use App\Enums\DeedClass;

/**
 * How a parcel stands in the portal's 3D view by what it is: land lies as a
 * low slab coloured by its deed class, buildings rise to a typical height.
 * One source for both the portfolio map and the parcel mini-map.
 */
final class ParcelMassing
{
    private const LAND_SLAB_M = 1.5;

    /** @var array<string, array{height: float, color: string}> */
    private const CATEGORIES = [
        'land_residential' => ['height' => self::LAND_SLAB_M, 'color' => '#3fa77f'],
        'land_agricultural' => ['height' => self::LAND_SLAB_M, 'color' => '#8c9a3c'],
        'land_industrial' => ['height' => self::LAND_SLAB_M, 'color' => '#7890a8'],
        'land_unclassified' => ['height' => self::LAND_SLAB_M, 'color' => '#c2ae86'],
        'villa' => ['height' => 9.0, 'color' => '#e6c364'],
        'building' => ['height' => 24.0, 'color' => '#5b7fb0'],
        'warehouse' => ['height' => 8.0, 'color' => '#a0714f'],
    ];

    /** A parcel with no asset type recorded is treated as land. */
    public static function categoryOf(?string $assetType, ?string $deedClass): string
    {
        return match (AssetType::tryFrom((string) $assetType)) {
            AssetType::Villa => 'villa',
            AssetType::Building, AssetType::Apartment => 'building',
            AssetType::Warehouse => 'warehouse',
            default => match (DeedClass::tryFrom((string) $deedClass)) {
                DeedClass::Residential => 'land_residential',
                DeedClass::Agricultural => 'land_agricultural',
                DeedClass::Industrial => 'land_industrial',
                null => 'land_unclassified',
            },
        };
    }

    /** @return array{height: float, color: string} */
    public static function styleOf(?string $assetType, ?string $deedClass): array
    {
        return self::CATEGORIES[self::categoryOf($assetType, $deedClass)];
    }

    /** @return array<string, array{height: float, color: string, label: string}> */
    public static function categories(): array
    {
        $out = [];
        foreach (self::CATEGORIES as $key => $style) {
            $out[$key] = $style + ['label' => __('portal.massing.'.$key)];
        }

        return $out;
    }
}
