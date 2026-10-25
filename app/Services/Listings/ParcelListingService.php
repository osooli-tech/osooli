<?php

declare(strict_types=1);

namespace App\Services\Listings;

use App\Enums\ListingStatus;
use App\Models\AuditLog;
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
     * area and place, and nothing about the deed, the price or the seller.
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
                'parcel.heldDeed',
                'parcel.plan.district.city',
                'parcel.boundary',
            ])
            ->latest('reviewed_at');
    }

    /**
     * The one shape every surface shows a listing in.
     *
     * @return array{id: int, area: float|null, asset_type: string|null, district: string|null, city: string|null, geojson: string|null, frontage: array<string, mixed>|null, contact_phone: string, whatsapp: string, listed_at: string|null}
     */
    public function present(ParcelListing $listing): array
    {
        $parcel = $listing->parcel;
        $deedArea = $parcel->heldDeed?->deed_area;
        $computed = $parcel->getAttribute('area_sqm');
        $district = $parcel->plan?->district;

        return [
            'id' => $listing->id,
            'area' => $deedArea !== null ? round((float) $deedArea, 2) : ($computed !== null ? round((float) $computed, 2) : null),
            'asset_type' => $parcel->asset_type,
            'district' => $district?->name_ar,
            'city' => $district?->city?->name_ar,
            'geojson' => $parcel->getAttribute('geom_json'),
            'frontage' => ParcelFrontage::read($parcel->boundary),
            'contact_phone' => $listing->contact_phone,
            'whatsapp' => self::internationalPhone($listing->contact_phone),
            'listed_at' => $listing->reviewed_at?->toDateString(),
        ];
    }

    /** 05XXXXXXXX, 5XXXXXXXX or +9665XXXXXXXX as 9665XXXXXXXX, the form WhatsApp links take. */
    public static function internationalPhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);

        return '966'.substr($digits, -9);
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
