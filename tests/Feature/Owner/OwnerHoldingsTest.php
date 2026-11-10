<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Enums\DeedStatus;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Services\Owner\OwnerHoldings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An owner's share of a parcel, read from the deed that holds it today.
 */
class OwnerHoldingsTest extends TestCase
{
    use RefreshDatabase;

    private Parcel $parcel;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create(['plan_no' => '3114']);
        $this->parcel = Parcel::create(['parcel_no' => '2018', 'geo_id' => '2018-3114', 'plan_id' => $plan->id]);
    }

    public function test_a_recorded_share_is_used(): void
    {
        [$a, $b] = [$this->owner('1'), $this->owner('2')];
        $this->deed(DeedStatus::Updated, [$a->id => 50, $b->id => 50]);

        $this->assertSame(0.5, app(OwnerHoldings::class)->share($this->parcel->fresh(), $a->id));
    }

    public function test_a_missing_share_splits_equally(): void
    {
        [$a, $b, $c] = [$this->owner('1'), $this->owner('2'), $this->owner('3')];
        $this->deed(DeedStatus::Updated, [$a->id => null, $b->id => null, $c->id => null]);

        $shares = app(OwnerHoldings::class)->shares($this->parcel->fresh());

        $this->assertCount(3, $shares);
        foreach ($shares as $share) {
            $this->assertEqualsWithDelta(1 / 3, $share, 0.0001);
        }
    }

    public function test_a_share_over_100_is_capped(): void
    {
        $a = $this->owner('1');
        $this->deed(DeedStatus::Updated, [$a->id => 140]);

        $this->assertSame(1.0, app(OwnerHoldings::class)->share($this->parcel->fresh(), $a->id));
    }

    public function test_an_owner_only_on_an_old_deed_holds_nothing(): void
    {
        [$seller, $buyer] = [$this->owner('1'), $this->owner('2')];
        $this->deed(DeedStatus::Old, [$seller->id => 100]);
        $this->deed(DeedStatus::Updated, [$buyer->id => 100]);

        $holdings = app(OwnerHoldings::class);
        $this->assertSame(0.0, $holdings->share($this->parcel->fresh(), $seller->id));
        $this->assertSame(1.0, $holdings->share($this->parcel->fresh(), $buyer->id));
    }

    public function test_a_parcel_with_no_held_deed_has_no_shares(): void
    {
        $this->assertSame([], app(OwnerHoldings::class)->shares($this->parcel->fresh()));
    }

    private function owner(string $n): Owner
    {
        return Owner::create(['name' => 'مالك '.$n, 'national_id' => '100000000'.$n]);
    }

    /** @param array<int, float|int|null> $shares owner id => share */
    private function deed(DeedStatus $status, array $shares): Deed
    {
        $deed = Deed::create(['parcel_id' => $this->parcel->id, 'deed_no' => (string) random_int(100000, 999999), 'deed_area' => 750, 'deed_status' => $status->value]);
        foreach ($shares as $ownerId => $share) {
            $deed->owners()->attach($ownerId, ['ownership_share' => $share]);
        }

        return $deed;
    }
}
