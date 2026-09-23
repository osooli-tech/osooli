<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Geo\GeometryMath;
use PHPUnit\Framework\TestCase;

class GeometryMathTest extends TestCase
{
    /** A 0.001° square near Riyadh — about 110.8 m × 101.2 m. */
    private const SQUARE = [[[[46.6, 24.7], [46.601, 24.7], [46.601, 24.701], [46.6, 24.701], [46.6, 24.7]]]];

    public function test_area_matches_the_ellipsoidal_area_of_a_small_square(): void
    {
        $measured = GeometryMath::measure(self::SQUARE);

        // 0.001° of latitude ≈ 110.77 m and of longitude ≈ 101.19 m at 24.7°N (WGS 84).
        $this->assertEqualsWithDelta(11209, $measured['area'], 15);
        $this->assertTrue($measured['valid']);
        $this->assertSame(5, $measured['points']);
        $this->assertSame([46.6, 24.7, 46.601, 24.701], $measured['bbox']);
    }

    public function test_a_hole_is_subtracted_from_the_area(): void
    {
        $withHole = [[
            self::SQUARE[0][0],
            [[46.6002, 24.7002], [46.6002, 24.7004], [46.6004, 24.7004], [46.6004, 24.7002], [46.6002, 24.7002]],
        ]];

        $full = GeometryMath::measure(self::SQUARE)['area'];
        $holed = GeometryMath::measure($withHole)['area'];

        $this->assertEqualsWithDelta($full * 0.96, $holed, 1);
    }

    public function test_a_bow_tie_is_reported_as_self_intersecting(): void
    {
        $bowTie = [[[[46.6, 24.7], [46.601, 24.701], [46.601, 24.7], [46.6, 24.701], [46.6, 24.7]]]];

        $this->assertSame('Self-intersection', GeometryMath::invalidReason($bowTie));
    }

    public function test_an_unclosed_or_short_ring_is_invalid(): void
    {
        $this->assertSame('Ring is not closed', GeometryMath::invalidReason(
            [[[[46.6, 24.7], [46.601, 24.7], [46.601, 24.701], [46.6, 24.701]]]]
        ));
        $this->assertSame('Too few points in geometry component', GeometryMath::invalidReason(
            [[[[46.6, 24.7], [46.601, 24.7], [46.6, 24.7]]]]
        ));
    }

    public function test_a_repeated_vertex_is_not_a_crossing(): void
    {
        $repeated = [[[[46.6, 24.7], [46.601, 24.7], [46.601, 24.7], [46.601, 24.701], [46.6, 24.701], [46.6, 24.7]]]];

        $this->assertNull(GeometryMath::invalidReason($repeated));
    }
}
