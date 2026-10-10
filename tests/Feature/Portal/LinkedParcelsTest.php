<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\DeedStatus;
use App\Enums\PhotoType;
use App\Livewire\Owners\OwnerIndex;
use App\Livewire\Settings\LinkedParcelsSettings;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\PortalSetting;
use App\Models\User;
use App\Services\Import\ParcelGeoJsonImporter;
use App\Services\Owner\OwnerInsightsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A parcel may stay under a "parent owner" although its deed names someone
 * else (land a father handed to his son). The parent goes on seeing it in the
 * portal, apart from their own holdings, with only as much detail as the
 * administrator's switches allow; a parcel with no parent owner simply left.
 */
class LinkedParcelsTest extends TestCase
{
    use RefreshDatabase;

    private Owner $father;

    private Owner $son;

    private Parcel $given;

    private Parcel $sold;

    private ParcelPhoto $sonsDeedScan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ParcelPhoto::PRIVATE_DISK);

        $this->father = Owner::create(['name' => 'الأب', 'national_id' => '1000000001', 'phone' => '0500000001']);
        $this->son = Owner::create(['name' => 'الابن', 'national_id' => '1000000002', 'phone' => '0500000002']);
        $buyer = Owner::create(['name' => 'المشتري', 'national_id' => '1000000003', 'phone' => '0500000003']);
        $plan = Plan::create(['plan_no' => '9']);

        // Given to the son: his deed, the father stays its parent owner.
        $this->given = Parcel::create(['parcel_no' => '501', 'geo_id' => 'geo-501', 'plan_id' => $plan->id, 'parent_owner_id' => $this->father->id, 'parcel_price' => 750000]);
        $sonsDeed = $this->deed($this->given, '444444', $this->son);

        // Sold: the buyer's deed and no parent owner.
        $this->sold = Parcel::create(['parcel_no' => '502', 'geo_id' => 'geo-502', 'plan_id' => $plan->id]);
        $this->deed($this->sold, '555555', $buyer);

        // Still the father's own.
        $own = Parcel::create(['parcel_no' => '503', 'geo_id' => 'geo-503', 'plan_id' => $plan->id, 'parent_owner_id' => $this->father->id]);
        $this->deed($own, '666666', $this->father);

        DB::update("UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))', 4326)");

        Storage::disk(ParcelPhoto::PRIVATE_DISK)->put('deeds/son.pdf', '%PDF-1.7');
        $this->sonsDeedScan = ParcelPhoto::create([
            'parcel_id' => $this->given->id, 'deed_id' => $sonsDeed->id, 'photo_url' => 'deeds/son.pdf',
            'storage_disk' => ParcelPhoto::PRIVATE_DISK, 'photo_type' => PhotoType::Deed->value,
        ]);
    }

    public function test_the_parent_owner_sees_the_given_parcel_but_not_the_sold_one(): void
    {
        $this->actingAs($this->father, 'owner')->get(route('portal.linked.index'))
            ->assertOk()
            ->assertSee('الابن')->assertSee('501')
            ->assertDontSee('المشتري')->assertDontSee('502');

        $this->get(route('portal.linked.show', $this->given->id))->assertOk()->assertSee('الابن');
        $this->get(route('portal.linked.show', $this->sold->id))->assertNotFound();
    }

    public function test_a_linked_parcel_never_counts_as_the_parents_own(): void
    {
        $this->assertSame(['503'], $this->father->parcels()->pluck('parcel_no')->all());
        $this->assertSame(['501'], $this->father->linkedParcels()->pluck('parcel_no')->all(), 'one they still hold is theirs, not linked');

        // The own-parcel page, and a change request on it, stay closed.
        $this->actingAs($this->father, 'owner')->get(route('portal.parcels.show', $this->given->id))->assertNotFound();
    }

    public function test_the_headline_figures_take_the_given_parcels_in(): void
    {
        $portfolio = app(OwnerInsightsService::class)->for($this->father)['portfolio'];

        // His own parcel plus the one given to his son; the sold one is gone.
        $this->assertSame(2, $portfolio['parcels']);
        $this->assertSame(1200.0, $portfolio['area']);
        $this->assertSame(750000.0, $portfolio['value']);

        PortalSetting::create(['linked_parcels' => [PortalSetting::SHOW_VALUE => false]]);
        $this->assertNull(app(OwnerInsightsService::class)->for($this->father)['portfolio']['value'], 'the value stays out once its switch is off');
    }

    public function test_staff_see_the_full_count_with_a_hint_and_the_given_parcels_as_portfolios(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        Livewire::test(OwnerIndex::class)
            ->set('search', 'الأب')
            ->assertSee(__('owners.linked_count', ['count' => 1]))
            ->call('toggleExpand', $this->father->id)
            ->assertSee(__('owners.linked_portfolio_of', ['name' => 'الابن']))
            ->assertSee('750,000');
    }

    public function test_nobody_else_reaches_it_and_the_son_is_unaffected(): void
    {
        $this->actingAs($this->son, 'owner')->get(route('portal.linked.index'))->assertOk()->assertDontSee('501');
        $this->get(route('portal.linked.show', $this->given->id))->assertNotFound();
        $this->get(route('portal.parcels.show', $this->given->id))->assertOk();
    }

    public function test_with_the_switches_off_only_the_parcel_itself_is_shown(): void
    {
        PortalSetting::create(['linked_parcels' => [
            PortalSetting::SHOW_VALUE => false, PortalSetting::SHOW_DEED => false, PortalSetting::SHOW_DOCUMENTS => false,
        ]]);

        $this->actingAs($this->father, 'owner')->get(route('portal.linked.show', $this->given->id))
            ->assertOk()
            ->assertDontSee('444444')
            ->assertDontSee('750,000')
            ->assertDontSee('1000000002');

        $this->get(route('portal.documents.download', $this->sonsDeedScan))->assertNotFound();
        $this->getJson(route('portal.parcels.documents', $this->given->id))->assertOk()->assertJsonCount(0, 'documents');
    }

    public function test_by_default_the_parent_owner_sees_it_as_their_own(): void
    {
        $this->actingAs($this->father, 'owner')->get(route('portal.linked.show', $this->given->id))
            ->assertOk()
            ->assertSee('444444')
            ->assertSee('750,000')
            ->assertDontSee('1000000002', false);

        $this->get(route('portal.documents.download', $this->sonsDeedScan))->assertOk();
        $this->getJson(route('portal.parcels.documents', $this->given->id))->assertOk()->assertJsonCount(1, 'documents');
    }

    public function test_the_map_feed_marks_linked_parcels_and_hides_what_is_switched_off(): void
    {
        $features = collect($this->actingAs($this->father, 'owner')->getJson(route('portal.geo.parcels'))->assertOk()->json('features'))
            ->keyBy('properties.parcel_no');

        $this->assertSame(['501', '503'], $features->keys()->map(fn ($k) => (string) $k)->sort()->values()->all());
        $this->assertTrue($features['501']['properties']['linked']);
        $this->assertSame('linked', $features['501']['properties']['massing']);
        $this->assertSame('444444', $features['501']['properties']['deed_no']);

        PortalSetting::create(['linked_parcels' => [PortalSetting::SHOW_VALUE => false, PortalSetting::SHOW_DEED => false]]);
        $hidden = collect($this->getJson(route('portal.geo.parcels'))->json('features'))->keyBy('properties.parcel_no');
        $this->assertNull($hidden['501']['properties']['deed_no']);
        $this->assertNull($hidden['501']['properties']['parcel_price']);
        $this->assertSame('الابن', $features['501']['properties']['owner_names']);
    }

    public function test_only_an_administrator_changes_the_switches(): void
    {
        Livewire::actingAs(User::factory()->create(['is_active' => true]))->test(LinkedParcelsSettings::class)->assertForbidden();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->givePermissionTo(Permission::findOrCreate('roles.manage', 'web'));

        Livewire::actingAs($admin)->test(LinkedParcelsSettings::class)
            ->assertSet('switches.'.PortalSetting::SHOW_VALUE, true)
            ->set('switches.'.PortalSetting::SHOW_VALUE, false)
            ->call('save');

        $this->assertFalse(PortalSetting::allows(PortalSetting::SHOW_VALUE));
        $this->assertTrue(PortalSetting::allows(PortalSetting::SHOW_DOCUMENTS));
    }

    public function test_an_import_sets_the_parent_owner_by_national_id(): void
    {
        $feature = json_decode((string) file_get_contents(base_path('tests/fixtures/import/parcels.geojson')), true)['features'][0];
        $feature['properties']['Parent_Owner_ID'] = '1000000001';
        $unknown = $feature;
        $unknown['properties']['Geo_ID'] = 'geo-unknown-parent';
        $unknown['properties']['Parent_Owner_ID'] = '9999999990';

        $result = app(ParcelGeoJsonImporter::class)->importFeatures([$feature, $unknown]);

        $this->assertSame($this->father->id, Parcel::where('geo_id', $feature['properties']['Geo_ID'])->value('parent_owner_id'));
        $this->assertNull(Parcel::where('geo_id', 'geo-unknown-parent')->value('parent_owner_id'));
        $this->assertStringContainsString('Parent_Owner_ID', implode(' ', $result->warnings), 'a number on no owner is reported, not guessed');
    }

    public function test_the_command_links_every_parcel_an_owner_handed_on(): void
    {
        // A parcel that was the father's and is the buyer's now, with no parent owner yet.
        $old = Deed::create(['parcel_id' => $this->sold->id, 'deed_no' => '000001', 'deed_area' => 600, 'deed_status' => DeedStatus::Old->value]);
        $old->owners()->attach($this->father->id);
        // Deed ids decide which is the held one: the buyer's must be the later.
        Deed::where('parcel_id', $this->sold->id)->where('deed_no', '555555')->delete();
        $this->deed($this->sold, '555556', Owner::where('name', 'المشتري')->firstOrFail());

        $this->artisan('owners:link-handed-parcels', ['owner' => $this->father->id, '--dry-run' => true])->assertSuccessful();
        $this->assertNull($this->sold->fresh()->parent_owner_id);

        $this->artisan('owners:link-handed-parcels', ['owner' => $this->father->id])->assertSuccessful();
        $this->assertSame($this->father->id, $this->sold->fresh()->parent_owner_id);
        $this->assertSame(['501', '502'], $this->father->linkedParcels()->orderBy('parcel_no')->pluck('parcel_no')->all());
    }

    private function deed(Parcel $parcel, string $no, Owner $owner): Deed
    {
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => $no, 'deed_area' => 600, 'deed_status' => DeedStatus::Updated->value]);
        $deed->owners()->attach($owner->id);

        return $deed;
    }
}
