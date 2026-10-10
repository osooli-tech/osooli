<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\DeedStatus;
use App\Enums\PhotoType;
use App\Livewire\Owners\OwnerIndex;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\User;
use App\Support\DocumentVault;
use App\Support\NationalId;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * National ids and document files are encrypted where they are stored, and
 * everything that depended on comparing a national id still works through
 * its fingerprint — above all, one number can still belong to only one owner.
 */
class EncryptionAtRestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_national_id_is_stored_encrypted_and_read_back_plain(): void
    {
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1098765432']);

        $stored = DB::table('owners')->where('id', $owner->id)->first();
        $this->assertStringNotContainsString('1098765432', (string) $stored->national_id);
        $this->assertSame(NationalId::hash('1098765432'), $stored->national_id_hash);
        $this->assertSame('1098765432', $owner->fresh()->national_id);
    }

    public function test_the_database_itself_refuses_a_second_owner_with_the_same_number(): void
    {
        Owner::create(['name' => 'الأول', 'national_id' => '1098765432']);

        $this->expectException(QueryException::class);
        Owner::create(['name' => 'الثاني', 'national_id' => '1098765432']);
    }

    public function test_the_same_number_typed_differently_is_still_a_duplicate(): void
    {
        Owner::create(['name' => 'الأول', 'national_id' => '1098765432']);

        $this->assertTrue(Owner::nationalIdTaken(' 1098 765432 '));
        $this->assertTrue(Owner::nationalIdTaken('١٠٩٨٧٦٥٤٣٢'), 'Arabic digits are the same number');
        $this->assertFalse(Owner::nationalIdTaken('1098765433'));
        $this->assertFalse(Owner::nationalIdTaken(null));
    }

    public function test_an_owner_keeps_their_own_number_when_edited_and_many_owners_may_have_none(): void
    {
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1098765432']);
        Owner::create(['name' => 'بلا رقم']);
        Owner::create(['name' => 'بلا رقم أيضًا']);

        $this->assertFalse(Owner::nationalIdTaken('1098765432', $owner->id));

        $owner->update(['national_id' => '2000000001']);
        $this->assertSame(NationalId::hash('2000000001'), DB::table('owners')->where('id', $owner->id)->value('national_id_hash'));
        $this->assertFalse(Owner::nationalIdTaken('1098765432'), 'the old number is free again');

        $owner->update(['national_id' => null]);
        $this->assertNull(DB::table('owners')->where('id', $owner->id)->value('national_id_hash'));
    }

    public function test_owners_are_found_by_the_full_number_only(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));
        Owner::create(['name' => 'صاحب الرقم', 'national_id' => '1098765432']);
        Owner::create(['name' => 'مالك آخر', 'national_id' => '2000000001']);

        Livewire::test(OwnerIndex::class)
            ->set('search', '1098765432')
            ->assertSee('صاحب الرقم')->assertDontSee('مالك آخر')
            ->set('search', '10987')
            ->assertDontSee('صاحب الرقم');
    }

    public function test_a_sealed_document_opens_to_the_same_bytes_and_a_tampered_one_is_refused(): void
    {
        $sealed = DocumentVault::seal('%PDF-1.7 deed');

        $this->assertStringNotContainsString('deed', $sealed);
        $this->assertSame('%PDF-1.7 deed', DocumentVault::open($sealed));
        $this->assertSame('%PDF-1.7 plain', DocumentVault::open('%PDF-1.7 plain'), 'a file never sealed is returned as it is');

        $this->expectException(RuntimeException::class);
        DocumentVault::open(substr($sealed, 0, -1).'X');
    }

    public function test_existing_files_are_encrypted_in_place_and_still_download_as_the_original(): void
    {
        Storage::fake(ParcelPhoto::PRIVATE_DISK);
        [$owner, $document] = $this->ownerWithDocument('%PDF-1.7 the deed itself');
        $disk = Storage::disk(ParcelPhoto::PRIVATE_DISK);

        $this->artisan('documents:encrypt', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('%PDF-1.7 the deed itself', $disk->get('deeds/x.pdf'));

        $this->artisan('documents:encrypt')->assertSuccessful();
        $this->assertTrue(DocumentVault::isSealed((string) $disk->get('deeds/x.pdf')));
        $this->assertStringNotContainsString('the deed itself', (string) $disk->get('deeds/x.pdf'));

        // Running it again changes nothing.
        $once = $disk->get('deeds/x.pdf');
        $this->artisan('documents:encrypt')->assertSuccessful();
        $this->assertSame($once, $disk->get('deeds/x.pdf'));

        $response = $this->actingAs($owner, 'owner')->get(route('portal.documents.download', $document));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.7 the deed itself', $response->streamedContent());
    }

    /** @return array{0: Owner, 1: ParcelPhoto} */
    private function ownerWithDocument(string $bytes): array
    {
        $owner = Owner::create(['name' => 'مالك', 'national_id' => '1000000001', 'phone' => '0500000001']);
        $parcel = Parcel::create(['parcel_no' => '7', 'geo_id' => 'geo-7', 'plan_id' => Plan::create(['plan_no' => '9'])->id]);
        Deed::create(['parcel_id' => $parcel->id, 'deed_no' => 'd', 'deed_area' => 500, 'deed_status' => DeedStatus::Updated->value])
            ->owners()->attach($owner->id);

        Storage::disk(ParcelPhoto::PRIVATE_DISK)->put('deeds/x.pdf', $bytes);
        $document = ParcelPhoto::create([
            'parcel_id' => $parcel->id, 'photo_url' => 'deeds/x.pdf', 'storage_disk' => ParcelPhoto::PRIVATE_DISK,
            'photo_type' => PhotoType::Deed->value,
        ]);

        return [$owner, $document];
    }
}
