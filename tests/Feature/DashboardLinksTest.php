<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeedStatus;
use App\Livewire\Dashboard\RecentAlerts;
use App\Livewire\Dashboard\RecentParcels;
use App\Livewire\ModificationRequests\RequestIndex;
use App\Livewire\Parcels\ParcelIndex;
use App\Models\Deed;
use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Every row and "view all" on the dashboard must lead somewhere real: the
 * recent parcels and deed alerts were plain text, and "view all" pointed back
 * at the dashboard itself. Owner scoping of the same lists is covered in
 * OwnerScopeTest.
 */
class DashboardLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recent_parcel_row_links_to_its_parcel_page(): void
    {
        $parcel = Parcel::create(['parcel_no' => '7', 'geo_id' => 'geo-7']);

        Livewire::actingAs($this->user())
            ->test(RecentParcels::class)
            ->assertSeeHtml('href="'.route('parcels.show', $parcel).'"');
    }

    public function test_recent_parcels_view_all_opens_the_parcel_list(): void
    {
        Parcel::create(['parcel_no' => '7', 'geo_id' => 'geo-7']);

        Livewire::actingAs($this->user('parcels.view'))
            ->test(RecentParcels::class)
            ->assertSeeHtml('href="'.route('parcels.index').'"')
            ->assertDontSeeHtml('href="'.route('dashboard').'"');
    }

    public function test_a_deed_alert_links_to_its_parcel_page(): void
    {
        $parcel = $this->parcelWithOldDeed();

        Livewire::actingAs($this->user())
            ->test(RecentAlerts::class)
            ->assertSeeHtml('href="'.route('parcels.show', $parcel).'"');
    }

    public function test_deed_alerts_view_all_opens_the_parcel_list_filtered_to_old_deeds(): void
    {
        $this->parcelWithOldDeed();

        Livewire::actingAs($this->user('parcels.view'))
            ->test(RecentAlerts::class)
            ->assertSeeHtml('href="'.e(route('parcels.index', ['deed_status' => DeedStatus::Old->value])).'"');
    }

    public function test_the_parcel_list_takes_its_deed_status_filter_from_the_url(): void
    {
        Livewire::withQueryParams(['deed_status' => DeedStatus::Old->value])
            ->actingAs($this->user('parcels.view'))
            ->test(ParcelIndex::class)
            ->assertSet('filterDeedStatus', DeedStatus::Old->value);
    }

    public function test_a_request_link_opens_that_request_on_the_modification_requests_page(): void
    {
        $parcel = Parcel::create(['parcel_no' => '7', 'geo_id' => 'geo-7']);
        $request = ModificationRequest::create([
            'parcel_id' => $parcel->id,
            'requested_by' => Owner::create(['name' => 'مالك'])->id,
            'field_name' => 'asset_type',
            'new_value' => 'سكني',
        ]);

        Livewire::withQueryParams(['request' => $request->id])
            ->actingAs($this->user('modification_requests.view'))
            ->test(RequestIndex::class)
            ->assertSet('viewingId', $request->id)
            ->assertSet('showModal', true);
    }

    public function test_the_dashboard_hides_parcel_documents_from_a_user_who_cannot_download_them(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="map"', false)
            ->assertSee('canViewDocuments: false', false)
            ->assertDontSee('deedDocument()?.download_url', false);
    }

    public function test_the_dashboard_shows_parcel_documents_to_a_user_who_can_download_them(): void
    {
        $this->withoutVite();

        $this->actingAs($this->user('documents.download'))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('canViewDocuments: true', false)
            ->assertSee('deedDocument()?.download_url', false);
    }

    private function parcelWithOldDeed(): Parcel
    {
        $parcel = Parcel::create(['parcel_no' => '7', 'geo_id' => 'geo-7']);
        Deed::create([
            'parcel_id' => $parcel->id,
            'deed_no' => 'deed-7',
            'deed_status' => DeedStatus::Old->value,
        ]);

        return $parcel;
    }

    private function user(string ...$permissions): User
    {
        $user = User::create([
            'name' => 'مستخدم',
            'email' => 'links-'.uniqid().'@sakuki.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
