<?php

declare(strict_types=1);

namespace App\Services\Listings;

use App\Enums\InquiryStatus;
use App\Enums\ListingStatus;
use App\Models\AuditLog;
use App\Models\ListingInquiry;
use App\Models\Owner;
use App\Models\Parcel;
use App\Models\ParcelListing;
use App\Models\User;
use App\Support\Database\Spatial;
use App\Support\ParcelFrontage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Offering a parcel for sale, from the owner's request through staff review
 * to what other owners are shown. The portal, the mobile API and the
 * dashboard all go through here, so the rules live in one place.
 */
class ParcelListingService
{
    private const AUDIT_TARGET = 'parcel_listing';

    /** The market map draws at most this many listings at once. */
    private const MAP_LIMIT = 500;

    /** Kept apart from the parcel page's other form, so each shows only its own errors. */
    public const ERROR_BAG = 'listing';

    /**
     * Opens a listing for review. The caller has already checked that the
     * owner holds the parcel; a co-owner may ask too — staff check with the
     * other holders before approving.
     */
    public function request(Owner $owner, Parcel $parcel, string $phone): ParcelListing
    {
        return DB::transaction(function () use ($owner, $parcel, $phone): ParcelListing {
            $open = ParcelListing::query()
                ->where('parcel_id', $parcel->getKey())
                ->whereIn('status', ListingStatus::openValues())
                ->lockForUpdate()
                ->exists();

            if ($open) {
                throw ValidationException::withMessages(['contact_phone' => __('listings.already_open')])->errorBag(self::ERROR_BAG);
            }

            $listing = ParcelListing::create([
                'parcel_id' => $parcel->getKey(),
                'owner_id' => $owner->getKey(),
                'contact_phone' => $phone,
                'status' => ListingStatus::Pending,
            ]);

            $this->audit('listing_requested', $listing, owner: $owner);

            return $listing;
        });
    }

    public function approve(ParcelListing $listing, User $by): void
    {
        $this->move($listing, ListingStatus::Approved, ['reviewed_by' => $by->getKey(), 'reviewed_at' => now()]);
        $this->audit('listing_approved', $listing, user: $by);
    }

    public function reject(ParcelListing $listing, User $by, string $reason): void
    {
        $this->move($listing, ListingStatus::Rejected, [
            'reviewed_by' => $by->getKey(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
        $this->audit('listing_rejected', $listing, user: $by);
    }

    /** By the seller, or by staff taking a listing down. */
    public function withdraw(ParcelListing $listing, ?Owner $owner = null, ?User $user = null): void
    {
        $this->move($listing, ListingStatus::Withdrawn, ['withdrawn_at' => now()]);
        $this->audit('listing_withdrawn', $listing, $user, $owner);
    }

    public function block(ParcelListing $listing, Owner $owner, User $by): void
    {
        if ($owner->getKey() === $listing->owner_id) {
            return;
        }

        $listing->blockedOwners()->syncWithoutDetaching([$owner->getKey() => ['created_by' => $by->getKey()]]);
        $this->audit('listing_blocked_owner', $listing, user: $by);
    }

    public function unblock(ParcelListing $listing, Owner $owner, User $by): void
    {
        $listing->blockedOwners()->detach($owner->getKey());
        $this->audit('listing_unblocked_owner', $listing, user: $by);
    }

    /**
     * What another owner may browse: the listing with its parcel's shape,
     * area, streets and place, and nothing about the deed, the price or the
     * seller — not even their number; Sukooki puts the two in touch.
     *
     * @return Builder<ParcelListing>
     */
    public function market(Owner $viewer): Builder
    {
        return ParcelListing::query()
            ->visibleTo($viewer)
            ->with([
                'parcel' => fn ($q) => $q->select('parcels.id', 'parcels.plan_id', 'parcels.asset_type')
                    ->selectRaw('ST_AsGeoJSON(parcels.geom, 6) AS geom_json')
                    ->selectRaw(Spatial::areaSqm('parcels.geom').' AS area_sqm'),
                'parcel.heldDeed.owners:id',
                'parcel.plan.district.city',
                'parcel.boundary',
                'inquiries' => fn ($q) => $q->where('owner_id', $viewer->getKey()),
            ])
            ->latest('reviewed_at');
    }

    /**
     * Every listing the viewer may see as one GeoJSON collection for the
     * market map — each with only what its popup shows.
     *
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public function marketMap(Owner $viewer): array
    {
        $features = [];
        foreach ($this->market($viewer)->limit(self::MAP_LIMIT)->get() as $listing) {
            $shown = $this->present($listing, $viewer);
            if ($shown['geojson'] === null) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => json_decode($shown['geojson'], true),
                'properties' => [
                    'id' => $shown['id'],
                    'area' => $shown['area'],
                    'asset_type' => $shown['asset_type'],
                    'place' => collect([$shown['district'], $shown['city']])->filter()->implode(' · '),
                    'streets' => $shown['streets'],
                    'is_mine' => $shown['is_mine'],
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * The one shape every surface shows a listing in.
     *
     * The viewer's own request to be put in touch is included when the
     * listing came from market(), which loads only theirs.
     *
     * @return array{id: int, area: float|null, asset_type: string|null, district: string|null, city: string|null, geojson: string|null, frontage: array<string, mixed>|null, streets: list<array{side: string, width: float|null}>, my_interest: string|null, is_mine: bool, listed_at: string|null}
     */
    public function present(ParcelListing $listing, ?Owner $viewer = null): array
    {
        $parcel = $listing->parcel;
        $deedArea = $parcel->heldDeed?->deed_area;
        $computed = $parcel->getAttribute('area_sqm');
        $district = $parcel->plan?->district;
        $frontage = ParcelFrontage::read($parcel->boundary);
        $streets = [];
        foreach ($frontage['streets'] ?? [] as $side => $width) {
            $streets[] = ['side' => (string) __('portal.side.'.$side), 'width' => $width];
        }
        $mine = $listing->relationLoaded('inquiries') ? $listing->inquiries->first() : null;

        return [
            'id' => $listing->id,
            'area' => $deedArea !== null ? round((float) $deedArea, 2) : ($computed !== null ? round((float) $computed, 2) : null),
            'asset_type' => $parcel->asset_type,
            'district' => $district?->name_ar,
            'city' => $district?->city?->name_ar,
            'geojson' => $parcel->getAttribute('geom_json'),
            'frontage' => $frontage,
            'streets' => $streets,
            'my_interest' => $mine?->status->value,
            // The seller and their co-holders: shown their own offer, never asked to be interested in it.
            'is_mine' => $viewer !== null && ($listing->owner_id === $viewer->getKey()
                || (bool) $parcel->heldDeed?->owners->contains('id', $viewer->getKey())),
            'listed_at' => $listing->reviewed_at?->toDateString(),
        ];
    }

    /**
     * A buyer asking Sukooki to put them in touch with the seller. Asking
     * again while a request is open changes nothing; a closed one reopens.
     */
    public function inquire(Owner $buyer, ParcelListing $listing, ?string $note): ListingInquiry
    {
        if ($this->present($listing, $buyer)['is_mine']) {
            throw ValidationException::withMessages(['note' => __('listings.interest_own')])->errorBag(self::ERROR_BAG);
        }

        $inquiry = ListingInquiry::query()->firstOrNew(['parcel_listing_id' => $listing->id, 'owner_id' => $buyer->getKey()]);

        if ($inquiry->exists && $inquiry->status !== InquiryStatus::Closed) {
            return $inquiry;
        }

        $inquiry->fill(['note' => $note, 'status' => InquiryStatus::New, 'handled_by' => null, 'handled_at' => null])->save();
        $this->audit('listing_inquiry', $listing, owner: $buyer);

        return $inquiry;
    }

    public function handleInquiry(ListingInquiry $inquiry, InquiryStatus $status, User $by): void
    {
        $inquiry->update(['status' => $status, 'handled_by' => $by->getKey(), 'handled_at' => now()]);
        $this->audit('listing_inquiry_'.$status->value, $inquiry->listing, user: $by);
    }

    /** @param  array<model-property<ParcelListing>, mixed>  $attributes */
    private function move(ParcelListing $listing, ListingStatus $to, array $attributes): void
    {
        if (! in_array($to, $listing->status->allowedTransitions(), true)) {
            throw ValidationException::withMessages(['status' => __('listings.invalid_transition')])->errorBag(self::ERROR_BAG);
        }

        $listing->update([...$attributes, 'status' => $to]);
    }

    private function audit(string $action, ParcelListing $listing, ?User $user = null, ?Owner $owner = null): void
    {
        AuditLog::create([
            'user_id' => $user?->getKey(),
            'owner_id' => $owner?->getKey(),
            'action' => $action,
            'target_type' => self::AUDIT_TARGET,
            'target_id' => $listing->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
