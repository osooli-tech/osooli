<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\DeedStatus;
use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\Plan;
use App\Models\PortalAccount;
use App\Models\PortalSetting;
use App\Models\User;
use App\Services\Portal\AccountOverviewService;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Bin Talib family as the office sees it: one account, five owners, and a
 * parcel three brothers share.
 */
class AccountOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const THIRD = 33.333;

    private PortalAccessService $access;

    private User $staff;

    private PortalAccount $office;

    /** @var array<string, Owner> */
    private array $family = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(PortalAccessService::class);
        $this->staff = User::factory()->create(['is_active' => true]);

        foreach (['najla' => ['نجلاء سعود عبدالله بن طالب', '1041721927'], 'saud' => ['سعود عبدالله سعود بن طالب', '1041721885'], 'abdullah' => ['عبدالله بن سعود بن عبدالله طالب', '1041721950'], 'ahmad' => ['احمد بن سعود عبدالله بن طالب', '1074588920'], 'bandar' => ['بندر بن سعود بن عبدالله طالب', '1041721968']] as $key => [$name, $nid]) {
            $this->family[$key] = Owner::create(['name' => $name, 'national_id' => $nid]);
        }

        $this->parcel('1066', '2516', 1200, 4560000, ['najla' => 100]);
        $this->parcel('1308', '1637', 5530, 65254000, ['saud' => 100]);
        $this->parcel('2018', '3114', 750, 6937500, ['abdullah' => self::THIRD, 'ahmad' => self::THIRD, 'bandar' => self::THIRD]);

        $this->office = $this->access->accountForPhone('536664026', 'مكتب ركن الشمال الذهبي للعقارات');
        foreach ($this->family as $owner) {
            $this->access->grant($this->office, $owner, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        }
    }

    public function test_the_family_account_sees_three_parcels_and_five_cards(): void
    {
        $overview = app(AccountOverviewService::class)->for($this->office, showValue: true);

        $this->assertSame(3, $overview['totals']['parcels']);
        $this->assertCount(5, $overview['owners']);
        $this->assertSame($this->family['najla']->id, $overview['owners'][0]['owner_id']);
    }

    public function test_each_brother_sees_parcel_2018_at_a_third(): void
    {
        $cards = collect(app(AccountOverviewService::class)->for($this->office, showValue: true)['owners'])->keyBy('owner_id');

        foreach (['abdullah', 'ahmad', 'bandar'] as $brother) {
            $card = $cards[$this->family[$brother]->id];
            $this->assertSame(1, $card['parcels']);
            $this->assertEqualsWithDelta(750 * 0.3333, $card['area'], 0.01);
            $this->assertEqualsWithDelta(6937500 * 0.3333, $card['value'], 0.01);
            $this->assertSame('2018', $card['items'][0]['parcel_no']);
            $this->assertEqualsWithDelta(0.3333, $card['items'][0]['share'], 0.00001);
        }
    }

    public function test_the_shared_parcel_counts_once_at_full_value(): void
    {
        $totals = app(AccountOverviewService::class)->for($this->office, showValue: true)['totals'];

        // The three thirds are stored as 33.33 each: 99.99% of parcel 2018.
        $this->assertEqualsWithDelta(4560000 + 65254000 + 6937500 * 0.9999, $totals['value'], 1);
        $this->assertEqualsWithDelta(1200 + 5530 + 750 * 0.9999, $totals['area'], 0.01);
    }

    public function test_a_partner_outside_the_account_leaves_only_the_insiders_share(): void
    {
        $this->access->revoke($this->office->grants()->where('owner_id', $this->family['bandar']->id)->firstOrFail(), $this->staff);

        $totals = app(AccountOverviewService::class)->for($this->office->fresh(), showValue: true)['totals'];

        $this->assertSame(3, $totals['parcels']);
        $this->assertEqualsWithDelta(4560000 + 65254000 + 6937500 * 0.6666, $totals['value'], 1);
    }

    public function test_values_hide_when_the_show_value_switch_is_off(): void
    {
        $overview = app(AccountOverviewService::class)->for($this->office, showValue: false);

        $this->assertNull($overview['totals']['value']);
        $this->assertNull($overview['owners'][0]['value']);
        $this->assertNull($overview['owners'][0]['items'][0]['value']);
    }

    public function test_the_map_carries_every_parcel_once_with_its_owners(): void
    {
        DB::update("UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.6 24.8, 46.601 24.8, 46.601 24.801, 46.6 24.801, 46.6 24.8)))', 4326)");

        $map = app(AccountOverviewService::class)->map($this->office);

        $this->assertSame('FeatureCollection', $map['type']);
        $this->assertCount(3, $map['features']);
        $shared = collect($map['features'])->firstWhere('properties.parcel_no', '2018');
        $this->assertCount(3, $shared['properties']['owner_ids']);
        $this->assertSame($this->family['abdullah']->id, $shared['properties']['owner_ids'][0]);
    }

    public function test_signing_in_with_several_owners_lands_on_the_overview(): void
    {
        config(['auth.mobile_otp.test_code' => '6666']);

        $this->post(route('portal.login.submit'), ['phone' => '0536664026'])->assertRedirect(route('portal.otp'));
        $this->post(route('portal.otp.verify'), ['otp' => '6666'])->assertRedirect(route('portal.overview'));

        $this->get(route('portal.overview'))
            ->assertOk()
            ->assertSee('مكتب ركن الشمال الذهبي للعقارات')
            ->assertSee('بندر بن سعود بن عبدالله طالب')
            ->assertSee('2018')
            ->assertSee('33.33');
    }

    public function test_the_overview_page_follows_the_show_value_switch(): void
    {
        PortalSetting::create(['linked_parcels' => [PortalSetting::SHOW_VALUE => false]]);

        $this->actingAsOwner($this->family['najla'], $this->office)->get(route('portal.overview'))
            ->assertOk()
            ->assertDontSee('4,560,000');
    }

    public function test_a_single_owner_account_is_redirected_to_the_dashboard(): void
    {
        $own = Owner::create(['name' => 'وحيد', 'national_id' => '1000000008', 'phone' => '0500000008']);

        $this->actingAsOwner($own)->get(route('portal.overview'))->assertRedirect(route('portal.dashboard'));
    }

    /** @param array<string, float|int> $holders family key => share */
    private function parcel(string $no, string $planNo, float $area, float $price, array $holders): Parcel
    {
        $plan = Plan::firstOrCreate(['plan_no' => $planNo]);
        $parcel = Parcel::create(['parcel_no' => $no, 'geo_id' => $no.'-'.$planNo, 'plan_id' => $plan->id, 'parcel_price' => $price]);
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => $no.'000', 'deed_area' => $area, 'deed_status' => DeedStatus::Updated->value]);

        foreach ($holders as $key => $share) {
            $deed->owners()->attach($this->family[$key]->id, ['ownership_share' => $share]);
        }

        return $parcel;
    }
}
