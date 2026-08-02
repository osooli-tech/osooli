<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\PhotoType;
use App\Models\City;
use App\Models\Country;
use App\Models\Deed;
use App\Models\District;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelPhoto;
use App\Models\Plan;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the mobile API contract: the response envelope, and the rule that an
 * owner can only ever reach their own records.
 */
class OwnerApiTest extends TestCase
{
    use RefreshDatabase;

    private Owner $owner;

    private Owner $otherOwner;

    private Parcel $ownedParcel;

    private Parcel $foreignParcel;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::create(['name_ar' => 'السعودية']);
        $region = Region::create(['country_id' => $country->id, 'name_ar' => 'الرياض']);
        $city = City::create(['region_id' => $region->id, 'name_ar' => 'الدرعية']);
        $district = District::create(['city_id' => $city->id, 'name_ar' => 'العمارية']);
        $plan = Plan::create(['plan_no' => '25', 'district_id' => $district->id]);

        $this->owner = Owner::create([
            'name' => 'مالك تجريبي',
            'national_id' => '1000000001',
            'phone' => '0500000001',
        ]);

        $this->otherOwner = Owner::create([
            'name' => 'مالك آخر',
            'national_id' => '1000000002',
            'phone' => '0500000002',
        ]);

        $this->ownedParcel = $this->makeParcel($plan->id, '101', 'geo-101', $this->owner);
        $this->foreignParcel = $this->makeParcel($plan->id, '202', 'geo-202', $this->otherOwner);
    }

    public function test_verify_otp_returns_a_token_for_a_registered_phone(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0500000001'])
            ->assertOk();

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0500000001', 'otp' => '6666'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'owner' => ['id', 'name', 'national_id', 'phone']]]);
    }

    public function test_verify_otp_rejects_a_wrong_code(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0500000001']);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0500000001', 'otp' => '0000'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['otp']]);
    }

    public function test_request_otp_reports_an_unregistered_phone_as_not_found(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0599999999'])
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_messages_follow_the_accept_language_header(): void
    {
        // A real browser or Flutter client sends a weighted list, not a bare tag.
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0599999999'], [
            'Accept-Language' => 'ar-SA,ar;q=0.9,en;q=0.8',
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'رقم الجوال غير مسجّل');

        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0599999999'], [
            'Accept-Language' => 'en-US,en;q=0.9',
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Phone number is not registered');
    }

    public function test_an_unsupported_language_falls_back_instead_of_erroring(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '0599999999'], [
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Phone number is not registered');
    }

    public function test_endpoints_reject_unauthenticated_requests(): void
    {
        foreach (['/api/v1/me', '/api/v1/parcels', '/api/v1/dashboard', '/api/v1/deeds'] as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(401)
                ->assertJsonStructure(['message']);
        }
    }

    public function test_parcels_index_is_paginated_and_lists_only_owned_parcels(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/v1/parcels')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'parcel_no', 'asset_type', 'city', 'district', 'current_deed']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.id', $this->ownedParcel->id);
    }

    public function test_per_page_is_capped_so_a_client_cannot_request_an_unbounded_page(): void
    {
        $this->actingAsOwner()->getJson('/api/v1/parcels?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_parcel_detail_returns_the_documented_shape(): void
    {
        $this->actingAsOwner()->getJson("/api/v1/parcels/{$this->ownedParcel->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'parcel_no', 'asset_type', 'city', 'district',
                    'centroid', 'corners', 'geometry', 'owners', 'deeds',
                    'boundary', 'survey_decision', 'documents',
                ],
            ]);
    }

    public function test_parcel_detail_never_exposes_prices(): void
    {
        $response = $this->actingAsOwner()->getJson("/api/v1/parcels/{$this->ownedParcel->id}")
            ->assertOk();

        $this->assertStringNotContainsString('m_price', $response->getContent());
        $this->assertStringNotContainsString('parcel_price', $response->getContent());
    }

    public function test_another_owners_parcel_is_reported_as_not_found(): void
    {
        $this->actingAsOwner()->getJson("/api/v1/parcels/{$this->foreignParcel->id}")
            ->assertStatus(404)
            ->assertJsonStructure(['message']);
    }

    public function test_another_owners_document_is_reported_as_not_found(): void
    {
        $foreignDocument = ParcelPhoto::create([
            'parcel_id' => $this->foreignParcel->id,
            'photo_url' => '/storage/documents/deeds/foreign.pdf',
            'photo_type' => PhotoType::Deed->value,
        ]);

        $this->actingAsOwner()->getJson("/api/v1/documents/{$foreignDocument->id}/download")
            ->assertStatus(404);
    }

    public function test_dashboard_counts_only_the_signed_in_owners_records(): void
    {
        $this->actingAsOwner()->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.parcels_total', 1)
            ->assertJsonStructure([
                'data' => ['greeting_name', 'stats', 'by_city', 'by_district'],
            ]);
    }

    public function test_deeds_index_is_paginated_and_scoped_to_the_owner(): void
    {
        $this->actingAsOwner()->getJson('/api/v1/deeds')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'data' => [['id', 'deed_no', 'deed_status', 'parcel']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_modification_request_is_created_for_an_owned_parcel(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/modification-requests', [
            'parcel_id' => $this->ownedParcel->id,
            'field_name' => 'asset_type',
            'new_value' => 'فيلا',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'status', 'status_label', 'message']]);
    }

    public function test_modification_request_rejects_an_unowned_parcel(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/modification-requests', [
            'parcel_id' => $this->foreignParcel->id,
            'field_name' => 'asset_type',
            'new_value' => 'فيلا',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['parcel_id']]);
    }

    public function test_modification_request_rejects_a_field_that_is_not_editable(): void
    {
        $this->actingAsOwner()->postJson('/api/v1/modification-requests', [
            'parcel_id' => $this->ownedParcel->id,
            'field_name' => 'parcel_no',
            'new_value' => '999',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['field_name']]);
    }

    public function test_profile_update_ignores_officially_sourced_fields(): void
    {
        $this->actingAsOwner()->patchJson('/api/v1/me', [
            'email' => 'owner@example.test',
            'name' => 'اسم مزوّر',
        ])->assertOk();

        $this->owner->refresh();

        $this->assertSame('owner@example.test', $this->owner->email);
        $this->assertSame('مالك تجريبي', $this->owner->name);
    }

    public function test_an_unexpected_failure_is_answered_generically_without_internals(): void
    {
        Log::spy();

        Route::middleware('auth:sanctum')->get('/api/v1/testing-explode', function (): void {
            throw new RuntimeException('database credentials leaked in this message');
        });

        $response = $this->actingAsOwner()->getJson('/api/v1/testing-explode')
            ->assertStatus(500)
            ->assertJsonStructure(['message']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('database credentials leaked', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('vendor', $body);
    }

    private function actingAsOwner(): static
    {
        return $this->withToken($this->owner->createToken('test')->plainTextToken);
    }

    /** Creates a parcel with geometry, a deed and an owner link. */
    private function makeParcel(int $planId, string $parcelNo, string $geoId, Owner $owner): Parcel
    {
        $parcel = Parcel::create([
            'parcel_no' => $parcelNo,
            'geo_id' => $geoId,
            'plan_id' => $planId,
            'm_price' => 1500,
            'parcel_price' => 1500000,
        ]);

        DB::update(
            "UPDATE parcels
             SET geom = ST_SetSRID(ST_GeomFromText('MULTIPOLYGON(((46.3 24.7, 46.4 24.7, 46.4 24.8, 46.3 24.8, 46.3 24.7)))'), 4326),
                 asset_type = 'أرض'
             WHERE id = ?",
            [$parcel->id]
        );

        $deed = Deed::create([
            'parcel_id' => $parcel->id,
            'deed_no' => "deed-{$parcelNo}",
            'deed_date_hijri' => '1442-04-21',
            'deed_area' => 1000,
        ]);

        DB::update("UPDATE deeds SET deed_status = 'محدث' WHERE id = ?", [$deed->id]);
        $deed->owners()->attach($owner->id);

        return $parcel->refresh();
    }
}
