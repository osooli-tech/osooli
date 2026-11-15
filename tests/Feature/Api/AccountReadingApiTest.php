<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\DeedStatus;
use App\Enums\PhotoType;
use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\PortalAccount;
use App\Models\User;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The app asks to read as the whole account (X-Read-Scope: account), as the
 * web portal reads: the land of every owner the account opens, on the list,
 * the map, the deeds, the search and the home figures. Only reads: a parcel
 * page and everything done on one stay with the owner who holds it.
 */
class AccountReadingApiTest extends TestCase
{
    use RefreshDatabase;

    private const SCOPE = ['X-Read-Scope' => 'account'];

    private PortalAccessService $access;

    private User $staff;

    private Owner $najla;

    private Owner $saud;

    private Owner $stranger;

    private PortalAccount $office;

    private Parcel $najlasParcel;

    private Parcel $saudsParcel;

    private Parcel $shared;

    private Parcel $strangersParcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(PortalAccessService::class);
        $this->staff = User::factory()->create(['is_active' => true]);
        $this->najla = Owner::create(['name' => 'نجلاء العتيبي', 'national_id' => '1041721927']);
        $this->saud = Owner::create(['name' => 'سعود العتيبي', 'national_id' => '1041721885']);
        $this->stranger = Owner::create(['name' => 'غريب', 'national_id' => '1000000009']);

        $plan = Plan::create(['plan_no' => '2516']);
        $this->najlasParcel = $this->parcel($plan, '1066', [$this->najla]);
        $this->saudsParcel = $this->parcel($plan, '1308', [$this->saud]);
        $this->shared = $this->parcel($plan, '2018', [$this->najla, $this->saud]);
        $this->strangersParcel = $this->parcel($plan, '9999', [$this->stranger]);
        DB::update("UPDATE parcels SET geom = ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.31 24.7, 46.31 24.71, 46.3 24.71, 46.3 24.7)))', 4326)");

        $this->office = $this->access->accountForPhone('0536664026', 'مكتب ركن الشمال الذهبي');
        $this->access->setIdentity($this->office, PortalAccount::KIND_COMPANY, 'مكتب ركن الشمال الذهبي', '7003374043', $this->staff);
        $this->access->grant($this->office, $this->najla, PortalRelation::Delegate, true, PortalGrantSource::Staff, $this->staff);
        $this->access->grant($this->office, $this->saud, PortalRelation::Delegate, false, PortalGrantSource::Staff, $this->staff);
    }

    public function test_without_the_header_the_app_reads_the_token_owner_only_as_before(): void
    {
        $this->apiAsOwner($this->najla, $this->office);

        $this->assertSame(['1066', '2018'], $this->parcelNos($this->getJson('/api/v1/parcels')));
        $this->getJson('/api/v1/parcels')->assertJsonMissingPath('data.0.holder_ids');
    }

    public function test_with_the_header_the_list_holds_every_owners_land_once(): void
    {
        $this->apiAsOwner($this->najla, $this->office);

        $response = $this->withHeaders(self::SCOPE)->getJson('/api/v1/parcels')->assertOk();

        $this->assertSame(['1066', '1308', '2018'], $this->parcelNos($response));
        $rows = collect($response->json('data'))->keyBy('parcel_no');
        $this->assertSame([$this->saud->id], $rows['1308']['holder_ids']);
        $this->assertEqualsCanonicalizing([$this->najla->id, $this->saud->id], $rows['2018']['holder_ids']);
    }

    public function test_the_map_deeds_search_and_home_read_the_whole_account(): void
    {
        $this->apiAsOwner($this->najla, $this->office);
        $this->withHeaders(self::SCOPE);

        $features = $this->getJson('/api/v1/parcels/map')->assertOk()->json('features');
        $this->assertEqualsCanonicalizing(['1066', '1308', '2018'], array_column(array_column($features, 'properties'), 'parcel_no'));

        $deeds = $this->getJson('/api/v1/deeds')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing(['D1066', 'D1308', 'D2018'], array_column($deeds, 'deed_no'));

        $this->getJson('/api/v1/search?q=1308&type=parcel')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->saudsParcel->id)
            ->assertJsonPath('data.0.holder_ids', [$this->saud->id]);

        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.stats.parcels_total', 3)
            // A company goes by its own name.
            ->assertJsonPath('data.greeting_name', 'مكتب ركن الشمال الذهبي');
    }

    public function test_a_shared_deed_list_names_the_holders_and_hides_withheld_scans(): void
    {
        $this->access->setPermissions($this->office->grants()->where('owner_id', $this->saud->id)->firstOrFail(), ['documents' => false], $this->staff);
        foreach ([$this->najlasParcel, $this->saudsParcel] as $parcel) {
            ParcelPhoto::create(['parcel_id' => $parcel->id, 'deed_id' => $parcel->deeds()->first()->id, 'photo_url' => 'd/'.$parcel->id.'.pdf', 'storage_disk' => ParcelPhoto::PRIVATE_DISK, 'photo_type' => PhotoType::Deed, 'status' => ParcelPhoto::STATUS_APPROVED]);
        }

        $this->apiAsOwner($this->najla, $this->office);
        $deeds = collect($this->withHeaders(self::SCOPE)->getJson('/api/v1/deeds')->assertOk()->json('data'))->keyBy('deed_no');

        $this->assertSame([$this->saud->id], $deeds['D1308']['holder_ids']);
        $this->assertNull($deeds['D1308']['document']);
        $this->assertNotNull($deeds['D1066']['document']);
    }

    public function test_the_cached_home_never_greets_with_another_accounts_name(): void
    {
        $own = $this->access->accountForPhone('0500000001');
        $this->access->grant($own, $this->saud, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);

        // Saud's own app fills the cache for Saud alone...
        $this->apiAsOwner($this->saud, $own)->getJson('/api/v1/dashboard')->assertJsonPath('data.greeting_name', 'سعود');
        // ...the company reading the same owner is still greeted by its own name,
        Auth::forgetGuards();
        $this->apiAsOwner($this->saud, $this->office)->getJson('/api/v1/dashboard')->assertJsonPath('data.greeting_name', 'مكتب ركن الشمال الذهبي');
        // ...and Saud's app is not greeted by the company's.
        Auth::forgetGuards();
        $this->apiAsOwner($this->saud, $own)->getJson('/api/v1/dashboard')->assertJsonPath('data.greeting_name', 'سعود');
    }

    public function test_the_whole_account_never_reaches_an_owner_it_does_not_open(): void
    {
        $this->apiAsOwner($this->najla, $this->office);
        $this->withHeaders(self::SCOPE);

        $this->assertNotContains('9999', $this->parcelNos($this->getJson('/api/v1/parcels')));
        $this->getJson('/api/v1/search?q=9999&type=parcel')->assertOk()->assertJsonCount(0, 'data');
        $this->assertNotContains('D9999', array_column($this->getJson('/api/v1/deeds')->json('data'), 'deed_no'));
    }

    public function test_a_revoked_owner_leaves_the_accounts_reading_at_once(): void
    {
        $token = $this->apiToken($this->najla);
        $this->access->revoke($this->office->grants()->where('owner_id', $this->saud->id)->firstOrFail(), $this->staff);
        Auth::forgetGuards();

        $response = $this->withToken($token)->withHeaders(self::SCOPE)->getJson('/api/v1/parcels')->assertOk();
        $this->assertSame(['1066', '2018'], $this->parcelNos($response));
    }

    public function test_a_parcel_page_stays_with_the_owner_who_holds_it(): void
    {
        $this->apiAsOwner($this->najla, $this->office);
        $this->withHeaders(self::SCOPE);

        $this->getJson("/api/v1/parcels/{$this->saudsParcel->id}")->assertNotFound();
        $this->getJson("/api/v1/parcels/{$this->shared->id}")->assertOk();
    }

    public function test_acting_ignores_the_header(): void
    {
        $this->apiAsOwner($this->najla, $this->office);
        $this->withHeaders(self::SCOPE);

        // Saud's parcel cannot be offered or changed in Najla's name.
        $this->postJson("/api/v1/parcels/{$this->saudsParcel->id}/listing", ['contact_phone' => '0536664026'])->assertNotFound();
        $this->postJson('/api/v1/modification-requests', ['parcel_id' => $this->saudsParcel->id, 'field_name' => 'asset_type', 'new_value' => 'أرض'])
            ->assertStatus(422);
    }

    public function test_an_owner_on_their_own_reads_the_same_with_or_without_the_header(): void
    {
        $own = $this->access->accountForPhone('0500000001');
        $this->access->grant($own, $this->saud, PortalRelation::Self_, true, PortalGrantSource::Staff, $this->staff);
        $this->apiAsOwner($this->saud, $own);

        $response = $this->withHeaders(self::SCOPE)->getJson('/api/v1/parcels')->assertOk();
        $this->assertSame(['1308', '2018'], $this->parcelNos($response));
        $response->assertJsonMissingPath('data.0.holder_ids');
    }

    /** @return list<string> */
    private function parcelNos(TestResponse $response): array
    {
        $nos = array_column($response->json('data'), 'parcel_no');
        sort($nos);

        return $nos;
    }

    private function apiToken(Owner $owner): string
    {
        $issued = $owner->createToken('mobile');
        $issued->accessToken->forceFill(['portal_account_id' => $this->office->id])->save();

        return $issued->plainTextToken;
    }

    /** @param  list<Owner>  $owners */
    private function parcel(Plan $plan, string $no, array $owners): Parcel
    {
        $parcel = Parcel::create(['parcel_no' => $no, 'geo_id' => 'geo-'.$no, 'plan_id' => $plan->id]);
        $deed = Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'D'.$no, 'deed_area' => 500, 'deed_status' => DeedStatus::Updated->value]);
        foreach ($owners as $owner) {
            $deed->owners()->attach($owner->id);
        }

        return $parcel;
    }
}
