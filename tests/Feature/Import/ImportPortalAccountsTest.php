<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Enums\PortalGrantSource;
use App\Enums\PortalRelation;
use App\Models\Owner;
use App\Models\PortalAccount;
use App\Models\PortalAccountOwner;
use App\Services\Import\ParcelGeoJsonImporter;
use App\Services\Portal\PortalAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A phone in an import file is the owner's portal sign-in. The same phone on
 * several owners — an office's number on a whole family — opens them all only
 * when staff said so for this import; anything else waits for review.
 */
class ImportPortalAccountsTest extends TestCase
{
    use RefreshDatabase;

    /** The office's first file: five owners, one phone, parcel 2018 shared by three. */
    private function binTalibFile(string $phone = '536664026'): array
    {
        $base = json_decode((string) file_get_contents(base_path('tests/fixtures/import/parcels.geojson')), true)['features'][0];
        $rows = [
            ['1066-2516', '917815000449', 'نجلاء سعود عبدالله بن طالب', '1041721927', 100.0],
            ['1308-1637', '393523002126', 'سعود عبدالله سعود بن طالب', '1041721885', 100.0],
            ['2018-3114', '511602003410', 'عبدالله بن سعود بن عبدالله طالب', '1041721950', 33.333],
            ['2018-3114', '511602003410', 'احمد بن سعود عبدالله بن طالب', '1074588920', 33.333],
            ['2018-3114', '511602003410', 'بندر بن سعود بن عبدالله طالب', '1041721968', 33.333],
        ];

        return array_map(function (array $row) use ($base, $phone): array {
            $feature = $base;
            $feature['properties'] = array_merge($base['properties'], [
                'Geo_ID' => $row[0], 'Deed_No' => $row[1], 'Name' => $row[2], 'Woner_ID' => $row[3], 'Share' => $row[4],
                'Phone' => $phone, 'Email' => 'goldenorth@outlook.com', 'Parent_Owner_ID' => null,
            ]);

            return $feature;
        }, $rows);
    }

    public function test_with_shared_phones_on_one_account_opens_the_five_owners(): void
    {
        app(ParcelGeoJsonImporter::class)->importFeatures($this->binTalibFile(), ['shared_phones' => true]);

        $account = PortalAccount::findByPhone('0536664026');
        $this->assertCount(5, $account?->usableOwnerIds() ?? []);
        $this->assertSame(0, PortalAccountOwner::where('needs_review', true)->count());
        $this->assertSame(5, Owner::where('phone_normalized', '536664026')->count());
    }

    public function test_with_shared_phones_off_one_owner_opens_and_four_wait_for_review(): void
    {
        $result = app(ParcelGeoJsonImporter::class)->importFeatures($this->binTalibFile());

        $account = PortalAccount::findByPhone('0536664026');
        $this->assertCount(1, $account?->usableOwnerIds() ?? []);
        $this->assertSame(4, PortalAccountOwner::where('needs_review', true)->count());
        $this->assertSame(1, Owner::where('phone_normalized', '536664026')->count(), 'a waiting phone is not written on the owner');
        $this->assertStringContainsString('536664026', implode(' ', $result->warnings));
    }

    public function test_a_phone_differing_from_the_record_is_not_written_and_waits(): void
    {
        $owner = Owner::create(['name' => 'نجلاء', 'national_id' => '1041721927']);
        $access = app(PortalAccessService::class);
        $access->grant($access->accountForPhone('0500000001'), $owner, PortalRelation::Self_, true, PortalGrantSource::Staff, null);

        app(ParcelGeoJsonImporter::class)->importFeatures([$this->binTalibFile('0500000002')[0]], ['shared_phones' => true]);

        $this->assertSame('0500000001', $owner->fresh()->phone);
        $this->assertSame([$owner->id], PortalAccount::findByPhone('0500000001')?->usableOwnerIds());
        $this->assertSame([], PortalAccount::findByPhone('0500000002')?->usableOwnerIds());
        $this->assertSame(1, PortalAccountOwner::where('needs_review', true)->count());
    }

    public function test_a_typo_matching_another_owner_does_not_grant_access(): void
    {
        $stranger = Owner::create(['name' => 'غريب', 'national_id' => '1000000009']);
        $access = app(PortalAccessService::class);
        $access->grant($access->accountForPhone('0500000009'), $stranger, PortalRelation::Self_, true, PortalGrantSource::Staff, null);

        app(ParcelGeoJsonImporter::class)->importFeatures([$this->binTalibFile('0500000009')[0]]);

        $this->assertSame([$stranger->id], PortalAccount::findByPhone('0500000009')?->usableOwnerIds());
    }

    public function test_reimporting_the_same_file_adds_no_grants(): void
    {
        app(ParcelGeoJsonImporter::class)->importFeatures($this->binTalibFile(), ['shared_phones' => true]);
        $before = PortalAccountOwner::count();

        app(ParcelGeoJsonImporter::class)->importFeatures($this->binTalibFile(), ['shared_phones' => true]);

        $this->assertSame($before, PortalAccountOwner::count());
    }

    public function test_the_option_is_off_unless_chosen(): void
    {
        $this->assertFalse(ParcelGeoJsonImporter::options([])['shared_phones']);
        $this->assertTrue(ParcelGeoJsonImporter::options(['shared_phones' => '1'])['shared_phones']);
    }
}
