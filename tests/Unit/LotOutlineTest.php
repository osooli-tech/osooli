<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LotOutline;
use PHPUnit\Framework\TestCase;

class LotOutlineTest extends TestCase
{
    public function test_a_lot_with_no_geometry_falls_back_to_a_square_facing_all_four_sides(): void
    {
        $outline = LotOutline::fit(null, 0, 0, 100, 100);

        $this->assertEqualsCanonicalizing([[0.0, 0.0], [100.0, 0.0], [100.0, 100.0], [0.0, 100.0]], $outline['points']);
        $this->assertEqualsCanonicalizing(['n', 'e', 's', 'w'], array_keys($outline['anchors']));
    }

    public function test_a_trapezoid_keeps_its_shape_and_names_each_edge_by_the_way_it_faces(): void
    {
        // Wide along the street to the south, narrow to the north; counter-clockwise as GeoJSON writes it.
        $outline = LotOutline::fit($this->polygon([[0, 0], [0.002, 0], [0.0015, 0.001], [0.0005, 0.001]]), 0, 0, 100, 100);

        $this->assertCount(4, $outline['points']);
        $this->assertEqualsCanonicalizing(['n', 'e', 's', 'w'], array_keys($outline['anchors']));

        $bySide = collect($outline['edges'])->keyBy('side');
        $this->assertGreaterThan($bySide['n']['length'], $bySide['s']['length'], 'the southern frontage is the longer edge');
        $this->assertLessThan($bySide['s']['y1'], $bySide['n']['y1'], 'north is up');
        $this->assertNotEquals(-90.0, $outline['anchors']['e']['angle'], 'a slanted side keeps its slant');
    }

    public function test_survey_noise_along_a_straight_side_does_not_split_it(): void
    {
        // A tiny kink in the middle of the southern edge.
        $outline = LotOutline::fit($this->polygon([[0, 0], [0.001, 0.000001], [0.002, 0], [0.002, 0.001], [0, 0.001]]), 0, 0, 100, 100);

        $this->assertCount(4, $outline['points']);
        $this->assertCount(1, array_filter($outline['edges'], fn (array $e) => $e['side'] === 's'));
    }

    public function test_text_on_every_side_stays_upright(): void
    {
        $outline = LotOutline::fit($this->polygon([[0, 0], [0.002, 0.0003], [0.0018, 0.0012], [0.0001, 0.001]]), 0, 0, 100, 100);

        foreach ($outline['anchors'] as $anchor) {
            $this->assertGreaterThan(-90, $anchor['angle']);
            $this->assertLessThanOrEqual(90, $anchor['angle']);
        }
    }

    public function test_a_lot_set_at_an_angle_takes_its_side_names_from_the_deed_lengths(): void
    {
        // A 200 x 140 m lot turned 30 degrees, long sides facing north and south; the deed calls the short sides north and south.
        $ring = [[0, 0], [0.001712, 0.000898], [0.001020, 0.001987], [-0.000692, 0.001089]];
        $dims = ['n' => 139.0, 'e' => 202.0, 's' => 139.0, 'w' => 202.0];

        $byGeometry = collect(LotOutline::fit($this->polygon($ring), 0, 0, 100, 100)['edges'])->keyBy('side');
        $byDeed = collect(LotOutline::fit($this->polygon($ring), 0, 0, 100, 100, $dims)['edges'])->keyBy('side');

        $this->assertGreaterThan($byGeometry['e']['length'], $byGeometry['n']['length'], 'facing alone puts a long edge on the north');
        $this->assertLessThan($byDeed['e']['length'], $byDeed['n']['length'], 'the deed puts the short edge on the north');
    }

    /** @param list<array{0: float|int, 1: float|int}> $ring */
    private function polygon(array $ring): string
    {
        $shifted = array_map(fn (array $p) => [46.7 + $p[0], 24.7 + $p[1]], $ring);

        return (string) json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[...$shifted, $shifted[0]]]]]);
    }
}
