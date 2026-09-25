<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Parcel;
use App\Support\Concerns\WritesSafely;
use App\Support\DatabaseEnum;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Every parcel column a person may correct — the geometry excepted.
 *
 * `geom` is absent from attributes() on purpose, not by oversight. A
 * MultiPolygon cannot be typed into a form field, and a polygon needs checks
 * this form has no business making — validity, plausible area, overlap — plus
 * a revision kept of the one it replaces. All of that lives in
 * Parcels\GeometryEditor and App\Support\ParcelGeometry.
 *
 * `source_gdb_id` and `last_synced_at` stay off attributes() too, and
 * unconditionally: they are sync bookkeeping the GDB import writes, not
 * something a person corrects from a form — the properties below only carry
 * them for display. `geo_id` is the odd one out: settable once, while
 * creating (attributes() in store() adds it back), then locked, because it is
 * the join key every later GDB sync matches this row by — changing it after
 * the fact orphans the parcel's history instead of updating it.
 */
class ParcelForm extends Form
{
    use WritesSafely;

    public ?int $parcelId = null;

    public string $parcelNo = '';

    public string $geoId = '';

    /** A `plans.id`, or '' when no plan is chosen. */
    public string $planId = '';

    /** A `parcels.id`, or '' for a top-level parcel. */
    public string $parentParcelId = '';

    public string $assetType = '';

    public string $landTransaction = '';

    public string $allocationMethod = '';

    public string $fallIn = '';

    public string $sourceGdbId = '';

    public string $lastSyncedAt = '';

    public string $mPrice = '';

    public string $parcelPrice = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $parentParcel = ['nullable', 'integer', Rule::exists('parcels', 'id')];

        if ($this->parcelId !== null) {
            // A self-referencing FK happily accepts a row that points at
            // itself, but Parcel::parent() would then never terminate and the
            // row could not be deleted without taking itself down the cascade.
            $parentParcel[] = Rule::notIn([$this->parcelId]);
        }

        return [
            'parcelNo' => ['nullable', 'string', 'max:50'],

            // The one column the table declares UNIQUE, and the key every GDB
            // import and every ArcGIS layer joins on — so it cannot be blank.
            'geoId' => [
                'required', 'string', 'max:100',
                Rule::unique('parcels', 'geo_id')->ignore($this->parcelId),
            ],

            'planId' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'parentParcelId' => $parentParcel,

            // A blank value skips every non-implicit rule in Laravel, so
            // 'nullable' alone carries "not chosen" here — all four enum
            // columns are nullable, and an offered value is always accepted
            // because DatabaseEnum reads the list off the column itself.
            'assetType' => ['nullable', DatabaseEnum::rule('asset_type')],
            'landTransaction' => ['nullable', DatabaseEnum::rule('land_transaction')],
            'allocationMethod' => ['nullable', DatabaseEnum::rule('allocation_method')],
            'fallIn' => ['nullable', DatabaseEnum::rule('fall_in')],

            // The bounds mirror the column precision. Postgres rounds a
            // decimal(12,2) or decimal(16,2) silently, so a value that does
            // not fit is refused here rather than stored as a different one.
            'mPrice' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'parcelPrice' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // The stock not_in message says only "invalid", which reads as a
            // broken dropdown rather than the one choice it actually refuses.
            'parentParcelId.not_in' => __('parcels.parent_is_self'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'parcelNo' => __('parcels.parcel_no'),
            'geoId' => __('parcels.geo_id'),
            'planId' => __('parcels.plan'),
            'parentParcelId' => __('parcels.parent_parcel'),
            'assetType' => __('parcels.asset_type'),
            'landTransaction' => __('parcels.land_transaction'),
            'allocationMethod' => __('parcels.allocation_method'),
            'fallIn' => __('parcels.fall_in'),
            'sourceGdbId' => __('parcels.source_gdb_id'),
            'lastSyncedAt' => __('parcels.last_synced_at'),
            'mPrice' => __('parcels.m_price'),
            'parcelPrice' => __('parcels.parcel_price'),
        ];
    }

    /** Load an existing parcel for editing, capturing the conflict baseline. */
    public function setParcel(Parcel $parcel): void
    {
        $this->parcelId = $parcel->id;
        $this->parcelNo = (string) $parcel->parcel_no;
        $this->geoId = (string) $parcel->geo_id;
        $this->planId = (string) $parcel->plan_id;
        $this->parentParcelId = (string) $parcel->parent_parcel_id;
        $this->assetType = (string) $parcel->asset_type;
        $this->landTransaction = (string) $parcel->land_transaction;
        $this->allocationMethod = (string) $parcel->allocation_method;
        $this->fallIn = (string) $parcel->fall_in;
        $this->sourceGdbId = (string) $parcel->source_gdb_id;
        // The model casts this column to a datetime while its declared type is
        // still the raw timestamp, so parse() handles either shape; the
        // `datetime-local` input wants exactly this format and nothing else.
        $syncedAt = $parcel->getAttribute('last_synced_at');
        $this->lastSyncedAt = $syncedAt === null ? '' : Carbon::parse($syncedAt)->format('Y-m-d\TH:i');
        $this->mPrice = (string) $parcel->m_price;
        $this->parcelPrice = (string) $parcel->parcel_price;
        $this->openedAt = $this->conflictBaseline($parcel);
    }

    public function store(): Parcel
    {
        $this->validate();

        return $this->writeSafely(
            'parcel.create',
            'parcel',
            null,
            // geo_id is only ever set here, while the row does not exist yet
            // — see the class docblock for why update() never writes it.
            fn (): Parcel => Parcel::create($this->attributes(includeGeoId: true))
        );
    }

    public function update(): Parcel
    {
        $this->validate();

        $parcel = Parcel::findOrFail($this->parcelId);

        $this->guardAgainstConflict($parcel, $this->openedAt);

        return $this->writeSafely(
            'parcel.update',
            'parcel',
            $parcel->id,
            function () use ($parcel): Parcel {
                $parcel->update($this->attributes());

                return $parcel->refresh();
            }
        );
    }

    /**
     * Form values mapped onto column names, with blanks stored as NULL so an
     * emptied field does not become an empty string the enum types reject.
     *
     * `geom` is absent deliberately — see the class docblock. So are
     * `geo_id` (store() adds it back for a brand-new row only) and
     * `source_gdb_id`/`last_synced_at` (never written from here, in either
     * direction — see the class docblock).
     *
     * @return array<string, string|int|null>
     */
    private function attributes(bool $includeGeoId = false): array
    {
        return [
            'parcel_no' => $this->orNull($this->parcelNo),
            ...($includeGeoId ? ['geo_id' => $this->geoId] : []),
            'plan_id' => $this->orIntNull($this->planId),
            'parent_parcel_id' => $this->orIntNull($this->parentParcelId),
            'asset_type' => $this->orNull($this->assetType),
            'land_transaction' => $this->orNull($this->landTransaction),
            'allocation_method' => $this->orNull($this->allocationMethod),
            'fall_in' => $this->orNull($this->fallIn),
            'm_price' => $this->orNull($this->mPrice),
            'parcel_price' => $this->orNull($this->parcelPrice),
        ];
    }

    private function orNull(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    private function orIntNull(string $value): ?int
    {
        return $value !== '' ? (int) $value : null;
    }
}
