<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ParcelMassing;
use PHPUnit\Framework\TestCase;

class ParcelMassingTest extends TestCase
{
    public function test_buildings_are_grouped_by_their_asset_type(): void
    {
        $this->assertSame('villa', ParcelMassing::categoryOf('فيلا', 'سكني'));
        $this->assertSame('building', ParcelMassing::categoryOf('عمارة', null));
        $this->assertSame('building', ParcelMassing::categoryOf('شقة', null));
        $this->assertSame('warehouse', ParcelMassing::categoryOf('مستودع', 'صناعي'));
    }

    public function test_land_is_grouped_by_its_deed_class(): void
    {
        $this->assertSame('land_residential', ParcelMassing::categoryOf('أرض', 'سكني'));
        $this->assertSame('land_agricultural', ParcelMassing::categoryOf('أرض', 'زراعي'));
        $this->assertSame('land_industrial', ParcelMassing::categoryOf('أرض', 'صناعي'));
        $this->assertSame('land_unclassified', ParcelMassing::categoryOf('أرض', null));
    }

    public function test_a_parcel_with_no_asset_type_is_treated_as_land(): void
    {
        $this->assertSame('land_agricultural', ParcelMassing::categoryOf(null, 'زراعي'));
        $this->assertSame('land_unclassified', ParcelMassing::categoryOf(null, null));
    }

    public function test_buildings_stand_taller_than_land(): void
    {
        $land = ParcelMassing::styleOf('أرض', 'سكني')['height'];
        $villa = ParcelMassing::styleOf('فيلا', null)['height'];
        $building = ParcelMassing::styleOf('عمارة', null)['height'];

        $this->assertLessThan($villa, $land);
        $this->assertLessThan($building, $villa);
    }
}
