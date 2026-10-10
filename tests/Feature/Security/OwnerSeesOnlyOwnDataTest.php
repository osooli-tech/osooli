<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\DeedStatus;
use App\Enums\PhotoType;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A parcel keeps the deeds of everyone who ever held it. Its present owner
 * sees their own deed and documents — never the earlier holder's name,
 * national id or deed scan, and never a co-owner's national id.
 */
class OwnerSeesOnlyOwnDataTest extends TestCase
{
    use RefreshDatabase;

    private Owner $current;

    private Owner $previous;

    private Owner $partner;

    private Parcel $parcel;

    private ParcelPhoto $ownScan;

    private ParcelPhoto $previousScan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ParcelPhoto::PRIVATE_DISK);

        $this->current = Owner::create(['name' => 'المالك الحالي', 'national_id' => '1000000001', 'phone' => '0500000001']);
        $this->previous = Owner::create(['name' => 'المالك السابق', 'national_id' => '1000000002', 'phone' => '0500000002']);
        $this->partner = Owner::create(['name' => 'الشريك', 'national_id' => '1000000003', 'phone' => '0500000003']);
        $this->parcel = Parcel::create(['parcel_no' => '77', 'geo_id' => 'geo-77', 'plan_id' => Plan::create(['plan_no' => '9'])->id]);

        $old = Deed::create(['parcel_id' => $this->parcel->id, 'deed_no' => '111111', 'deed_area' => 500, 'deed_status' => DeedStatus::Old->value]);
        $old->owners()->attach($this->previous->id);
        $new = Deed::create(['parcel_id' => $this->parcel->id, 'deed_no' => '222222', 'deed_area' => 500, 'deed_status' => DeedStatus::Updated->value]);
        $new->owners()->attach([$this->current->id => ['ownership_share' => 60], $this->partner->id => ['ownership_share' => 40]]);

        $this->previousScan = $this->scan('deeds/old.pdf', $old->id);
        $this->ownScan = $this->scan('deeds/new.pdf', $new->id);
    }

    public function test_of_the_earlier_holder_only_the_name_is_shown(): void
    {
        foreach (['portal.parcels.show', 'portal.parcels.twin'] as $route) {
            $this->actingAs($this->current, 'owner')->get(route($route, $this->parcel->id))
                ->assertOk()
                ->assertSee('222222')
                ->assertDontSee('111111')
                ->assertDontSee('1000000002');
        }

        // Who the parcel came from, by name — nothing else of theirs.
        $this->get(route('portal.parcels.show', $this->parcel->id))->assertSee('المالك السابق');
    }

    public function test_a_co_owner_is_named_but_their_national_id_is_not_shown(): void
    {
        $this->actingAs($this->current, 'owner')->get(route('portal.parcels.twin', $this->parcel->id))
            ->assertOk()
            ->assertSee('الشريك')
            ->assertSee('1000000001')
            ->assertDontSee('1000000003');
    }

    public function test_the_earlier_holders_deed_scan_is_neither_listed_nor_downloadable(): void
    {
        $listed = $this->actingAs($this->current, 'owner')
            ->getJson(route('portal.parcels.documents', $this->parcel->id))->assertOk()->json('documents.*.id');

        $this->assertSame([$this->ownScan->id], $listed);
        $this->get(route('portal.documents.download', $this->previousScan))->assertNotFound();
        $this->get(route('portal.documents.download', $this->ownScan))->assertOk();
    }

    public function test_the_mobile_api_follows_the_same_rule(): void
    {
        $token = $this->current->createToken('t')->plainTextToken;

        $detail = $this->withToken($token)->getJson("/api/v1/parcels/{$this->parcel->id}")->assertOk();
        $body = json_encode($detail->json(), JSON_UNESCAPED_UNICODE);

        $this->assertSame(['المالك السابق'], $detail->json('data.previous_owner_names') ?? $detail->json('previous_owner_names'));
        $this->assertStringNotContainsString('111111', (string) $body);
        $this->assertStringNotContainsString('1000000002', (string) $body);
        $this->assertStringNotContainsString('1000000003', (string) $body, 'a co-owner is named without their national id');
        $this->assertStringContainsString('الشريك', (string) $body);
        $this->assertStringContainsString('1000000001', (string) $body);

        $this->withToken($token)->getJson("/api/v1/parcels/{$this->parcel->id}/documents")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->get("/api/v1/documents/{$this->previousScan->id}/download")->assertNotFound();
        $this->withToken($token)->get("/api/v1/documents/{$this->ownScan->id}/download")->assertOk();
    }

    private function scan(string $path, int $deedId): ParcelPhoto
    {
        Storage::disk(ParcelPhoto::PRIVATE_DISK)->put($path, '%PDF-1.7');

        return ParcelPhoto::create([
            'parcel_id' => $this->parcel->id, 'deed_id' => $deedId, 'photo_url' => $path,
            'storage_disk' => ParcelPhoto::PRIVATE_DISK, 'photo_type' => PhotoType::Deed->value,
        ]);
    }
}
