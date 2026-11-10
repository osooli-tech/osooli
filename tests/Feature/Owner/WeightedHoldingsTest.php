<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Enums\DeedStatus;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Services\Owner\LinkedParcelsService;
use App\Services\Owner\OwnerInsightsService;
use App\Services\Owner\OwnerPortfolioService;
use App\Services\Owner\OwnerStatisticsService;
use App\Support\Database\Spatial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A co-owner's figures count their share of a parcel, not all of it, and a
 * deed that no longer holds the parcel counts for nothing.
 */
class WeightedHoldingsTest extends TestCase
{
    use RefreshDatabase;

    /** The file says 33.333; deed_owners.ownership_share keeps two decimals. */
    private const THIRD = 33.333;

    private Owner $abdullah;

    private Owner $ahmad;

    private Owner $bandar;

    private Owner $office;

    private Parcel $shared;

    private Parcel $own;

    protected function setUp(): void
    {
        parent::setUp();

        $this->abdullah = Owner::create(['name' => 'عبدالله', 'national_id' => '1041721950']);
        $this->ahmad = Owner::create(['name' => 'أحمد', 'national_id' => '1074588920']);
        $this->bandar = Owner::create(['name' => 'بندر', 'national_id' => '1041721968']);
        $this->office = Owner::create(['name' => 'المكتب', 'national_id' => '7003374043']);
        $plan = Plan::create(['plan_no' => '3114']);

        // Parcel 2018: one deed of 750 m² shared by three brothers.
        $this->shared = Parcel::create(['parcel_no' => '2018', 'geo_id' => '2018-3114', 'plan_id' => $plan->id, 'parcel_price' => 6937500, 'parent_owner_id' => $this->office->id]);
        $this->deed($this->shared, DeedStatus::Updated, 750, [$this->abdullah->id => self::THIRD, $this->ahmad->id => self::THIRD, $this->bandar->id => self::THIRD]);

        // Abdullah's own parcel: an old deed of 999 m² re-issued as 600 m².
        $this->own = Parcel::create(['parcel_no' => '77', 'geo_id' => '77-3114', 'plan_id' => $plan->id, 'parcel_price' => 1000000]);
        $this->deed($this->own, DeedStatus::Old, 999, [$this->abdullah->id => 100]);
        $this->deed($this->own, DeedStatus::Updated, 600, [$this->abdullah->id => 100]);
    }

    public function test_summary_area_counts_held_deeds_by_share(): void
    {
        $area = (new OwnerStatisticsService($this->abdullah))->summary()['area_total_sqm'];

        // A third of 750 plus the 600 m² deed in force; the superseded 999 m² is gone.
        $this->assertEqualsWithDelta(750 * 0.3333 + 600, $area, 0.01);
    }

    public function test_portfolio_value_uses_the_held_deed_share(): void
    {
        // A later deed on record that no longer holds the parcel names Ahmad
        // in full; his share is still the third on the deed in force.
        $this->deed($this->shared, DeedStatus::Old, 750, [$this->ahmad->id => 100]);

        $value = (new OwnerStatisticsService($this->ahmad))->portfolio()['total_value'];

        $this->assertEqualsWithDelta(6937500 * 0.3333, $value, 0.01);
    }

    public function test_insights_area_and_value_are_weighted(): void
    {
        $portfolio = app(OwnerInsightsService::class)->for($this->bandar)['portfolio'];

        $this->assertEqualsWithDelta(750 * 0.3333, $portfolio['area'], 0.01);
        $this->assertEqualsWithDelta(6937500 * 0.3333, $portfolio['value'], 0.01);
    }

    public function test_linked_groups_show_a_shared_deed_under_each_holder(): void
    {
        $groups = collect(app(LinkedParcelsService::class)->groups($this->office, forStaff: true))->keyBy('holder_id');

        $this->assertEqualsCanonicalizing([$this->abdullah->id, $this->ahmad->id, $this->bandar->id], $groups->keys()->all());

        foreach ($groups as $holderId => $group) {
            $this->assertSame(1, $group['parcels_count']);
            $row = $group['parcels'][0];
            $this->assertSame($this->shared->id, $row['id']);
            $this->assertEqualsWithDelta(0.3333, $row['share'], 0.00001);
            $this->assertEqualsWithDelta(750 * 0.3333, $row['area'], 0.01);
            $this->assertEqualsWithDelta(6937500 * 0.3333, $row['value'], 0.01);
            $this->assertSame(Owner::find($holderId)->name, $group['holder']);
        }
    }

    public function test_linked_totals_count_a_shared_parcel_once(): void
    {
        $totals = app(LinkedParcelsService::class)->totals($this->office, forStaff: true);

        $this->assertSame(1, $totals['parcels']);
        $this->assertEqualsWithDelta(750 * 0.9999, $totals['area'], 0.01);
        $this->assertEqualsWithDelta(6937500 * 0.9999, $totals['value'], 0.1);
    }

    public function test_portfolio_summaries_are_weighted_by_share(): void
    {
        DB::update("UPDATE parcels SET m_price = 9250, geom = ST_GeomFromText('MULTIPOLYGON(((46.60 24.80, 46.6003 24.80, 46.6003 24.8003, 46.60 24.8003, 46.60 24.80)))', 4326) WHERE id = ?", [$this->shared->id]);
        $geomArea = (float) DB::scalar('SELECT '.Spatial::areaSqm('geom').' FROM parcels WHERE id = ?', [$this->shared->id]);

        $portfolios = app(OwnerPortfolioService::class);
        $portfolio = $portfolios->create($this->abdullah, 'عبدالله');
        $portfolios->assign($this->abdullah, $this->shared, $portfolio);

        $summary = $portfolios->summary($portfolio);
        $this->assertSame(1, $summary['parcels']);
        $this->assertEqualsWithDelta($geomArea * 0.3333, $summary['area'], 0.01);
        $this->assertEqualsWithDelta(9250 * $geomArea * 0.3333, $summary['value'], 0.01);

        // Ahmad never split his land: the same parcel, his third, outside any portfolio.
        $unassigned = $portfolios->unassignedSummary($this->ahmad);
        $this->assertEqualsWithDelta($geomArea * 0.3333, $unassigned['area'], 0.01);
    }

    /** @param array<int, float|int|null> $shares */
    private function deed(Parcel $parcel, DeedStatus $status, float $area, array $shares): Deed
    {
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => (string) random_int(100000, 999999), 'deed_area' => $area, 'deed_status' => $status->value]);
        foreach ($shares as $ownerId => $share) {
            $deed->owners()->attach($ownerId, ['ownership_share' => $share]);
        }

        return $deed;
    }
}
