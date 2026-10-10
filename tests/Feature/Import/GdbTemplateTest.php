<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\User;
use App\Services\Import\GdbImporter;
use App\Support\NationalId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The Geodatabase template offered on the import screen: downloadable by
 * those who may import, and — imported as it is — filling every field the
 * import reads, the ones added since the first import among them.
 */
class GdbTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_downloaded_by_those_who_may_import(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get(route('imports.gdb.template'))->assertForbidden();

        Permission::firstOrCreate(['name' => 'imports.create', 'guard_name' => 'web']);
        $user->givePermissionTo('imports.create');

        $this->actingAs($user)->get(route('imports.gdb.template'))
            ->assertOk()
            ->assertDownload('Sakuki_Template.gdb.zip');
    }

    public function test_imported_as_it_is_it_fills_every_field(): void
    {
        $probe = new Process([(string) config('imports.ogr2ogr_path'), '--version']);
        $probe->run();
        if (! $probe->isSuccessful()) {
            $this->markTestSkipped('ogr2ogr (GDAL) is not installed on this machine.');
        }

        $country = DB::table('countries')->insertGetId(['name_ar' => 'السعودية', 'created_at' => now(), 'updated_at' => now()]);
        $region = DB::table('regions')->insertGetId(['name_ar' => 'منطقة الرياض', 'country_id' => $country, 'created_at' => now(), 'updated_at' => now()]);
        $city = DB::table('cities')->insertGetId(['name_ar' => 'العمارية', 'region_id' => $region, 'created_at' => now(), 'updated_at' => now()]);

        $zip = sys_get_temp_dir().'/gdb-template-'.Str::random(8).'.zip';
        copy(resource_path('templates/sakuki-gdb-template.zip'), $zip);

        try {
            $importer = app(GdbImporter::class);
            $analysis = $importer->analyze($zip)->toArray();
            $options = $analysis['details']['gdb']['suggested'];
            $options['default_city_id'] = $city;
            $options['portfolios'] = true;
            $options['qrar'] = 'source';

            $result = $importer->commit($zip, $options)->toArray();
        } finally {
            @unlink($zip);
        }

        $this->assertSame(0, $result['errors']);

        $parcel = DB::table('parcels')->where('geo_id', '131-623')->first();
        $this->assertNotNull($parcel);
        $this->assertEquals(850, $parcel->m_price);
        $this->assertEquals(1020000, $parcel->parcel_price);
        $this->assertSame('أرض', $parcel->asset_type);
        $this->assertSame('خاصة', $parcel->land_transaction);
        $this->assertSame('مخطط بلدية', $parcel->fall_in);

        $deed = DB::table('deeds')->where('deed_no', '310101000001')->first();
        $this->assertSame('1442-04-21', $deed->deed_date_hijri);
        $this->assertSame('سكني', $deed->deed_class);

        $shares = DB::table('deed_owners as o')->join('owners as w', 'w.id', '=', 'o.owner_id')
            ->where('o.deed_id', $deed->id)->orderBy('w.id')->pluck('o.ownership_share')->map(fn ($v) => (float) $v)->all();
        $this->assertSame([50.0, 50.0], $shares);

        $owner = DB::table('owners')->where('national_id_hash', NationalId::hash('1000000001'))->first();
        $this->assertSame('0500000001', $owner->phone);
        $this->assertNotNull($owner->phone_normalized);
        $this->assertSame('owner1@example.com', $owner->email);

        // A portfolio is each owner's own: both owners of the deed get one.
        $this->assertSame(2, DB::table('owner_portfolios')->where('name', 'أراضي العمارية')->count());

        $boundary = DB::table('parcel_boundaries')->where('parcel_id', $parcel->id)->first();
        $this->assertSame('شارع عرض 15م', $boundary->n_border);
        $this->assertSame('1445-02-10', $boundary->survey_date);

        $decision = DB::table('survey_decisions')->where('parcel_id', $parcel->id)->first();
        $this->assertSame('4501234', $decision->qrar_no);
        $this->assertSame('مكتب هندسي', $decision->qrar_source);
        $this->assertSame('R-2024-17', $decision->report_no);
    }
}
