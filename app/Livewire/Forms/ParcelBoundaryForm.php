<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Parcel;
use App\Models\ParcelBoundary;
use App\Support\Concerns\WritesSafely;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The surveyed boundary of one parcel — borders, side lengths, measured area.
 *
 * `parcel_boundaries.parcel_id` is UNIQUE, so a parcel carries at most one of
 * these rows. It may also carry none, and that is the case the screen this
 * replaces got wrong: see save().
 */
class ParcelBoundaryForm extends Form
{
    use WritesSafely;

    /** Null while the parcel has no boundary row yet. */
    public ?int $boundaryId = null;

    public ?int $parcelId = null;

    public string $nBorder = '';

    public string $sBorder = '';

    public string $eBorder = '';

    public string $wBorder = '';

    public string $nDim = '';

    public string $sDim = '';

    public string $eDim = '';

    public string $wDim = '';

    public string $measuredArea = '';

    /**
     * `matches_deed` is a nullable boolean and all three states are real: the
     * survey matched the deed, it did not, or nobody has checked yet. A
     * checkbox can only carry two, so it travels as '' | 'yes' | 'no'.
     */
    public string $matchesDeed = '';

    /** Stored as text, not a date: this date arrives Hijri as often as Gregorian. */
    public string $surveyDate = '';

    public string $engineeringOfficeId = '';

    /** `updated_at` as it stood when the record was opened; null when no row exists. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parcelId' => ['required', 'integer', Rule::exists('parcels', 'id')],

            'nBorder' => ['nullable', 'string', 'max:255'],
            'sBorder' => ['nullable', 'string', 'max:255'],
            'eBorder' => ['nullable', 'string', 'max:255'],
            'wBorder' => ['nullable', 'string', 'max:255'],

            // decimal(10,2): eight digits before the point, two after. Bounding
            // it here turns a typo into a field error rather than a Postgres
            // numeric-overflow exception on save.
            'nDim' => $this->dimensionRules(),
            'sDim' => $this->dimensionRules(),
            'eDim' => $this->dimensionRules(),
            'wDim' => $this->dimensionRules(),

            // decimal(14,2).
            'measuredArea' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],

            'matchesDeed' => ['nullable', 'in:yes,no'],

            // Shape only. The column is varchar(10) and the value may be a
            // Hijri date, which no date cast can parse — validating the shape
            // is the most this can honestly assert.
            'surveyDate' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],

            'engineeringOfficeId' => ['nullable', 'integer', Rule::exists('engineering_offices', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'parcelId' => __('survey_decisions.parcel_no'),
            'nBorder' => __('parcels.n_border'),
            'sBorder' => __('parcels.s_border'),
            'eBorder' => __('parcels.e_border'),
            'wBorder' => __('parcels.w_border'),
            'nDim' => __('survey_decisions.n_dim'),
            'sDim' => __('survey_decisions.s_dim'),
            'eDim' => __('survey_decisions.e_dim'),
            'wDim' => __('survey_decisions.w_dim'),
            'measuredArea' => __('survey_decisions.measured_area'),
            'matchesDeed' => __('survey_decisions.matches_deed'),
            'surveyDate' => __('survey_decisions.survey_date'),
            'engineeringOfficeId' => __('survey_decisions.engineering_office'),
        ];
    }

    /**
     * Load the parcel's boundary for editing, or blank fields when it has none.
     */
    public function setParcel(Parcel $parcel): void
    {
        $boundary = $parcel->boundary;

        $this->parcelId = $parcel->id;
        $this->boundaryId = $boundary?->id;

        $this->nBorder = (string) $boundary?->n_border;
        $this->sBorder = (string) $boundary?->s_border;
        $this->eBorder = (string) $boundary?->e_border;
        $this->wBorder = (string) $boundary?->w_border;

        $this->nDim = (string) $boundary?->n_dim;
        $this->sDim = (string) $boundary?->s_dim;
        $this->eDim = (string) $boundary?->e_dim;
        $this->wDim = (string) $boundary?->w_dim;

        $this->measuredArea = (string) $boundary?->measured_area;

        $this->matchesDeed = match ($boundary?->matches_deed) {
            true => 'yes',
            false => 'no',
            null => '',
        };

        $this->surveyDate = (string) $boundary?->survey_date;
        $this->engineeringOfficeId = (string) $boundary?->engineering_office_id;

        // A null baseline is correct here rather than a wiring mistake: there is
        // no row to conflict with until the first save creates one, so save()
        // only runs the guard when a row was actually loaded.
        $this->openedAt = $boundary !== null ? $this->conflictBaseline($boundary) : null;
    }

    public function save(): ParcelBoundary
    {
        $this->validate();

        $existing = ParcelBoundary::query()->where('parcel_id', $this->parcelId)->first();

        if ($existing !== null) {
            $this->guardAgainstConflict($existing, $this->openedAt);
        }

        $boundary = $this->writeSafely(
            $existing === null ? 'parcel_boundary.create' : 'parcel_boundary.update',
            'parcel_boundary',
            $existing?->id,
            // The silent failure this removes: the screen this replaces only
            // ever issued an UPDATE, so a parcel with no boundary row absorbed
            // every edit and stored none of them — no row, no error, no change.
            // Keying updateOrCreate on parcel_id makes the write an upsert: the
            // row is created when absent and updated when present. The table's
            // UNIQUE(parcel_id) still holds a parcel to exactly one boundary,
            // so two simultaneous creates fail loudly instead of duplicating.
            fn (): ParcelBoundary => ParcelBoundary::updateOrCreate(
                ['parcel_id' => $this->parcelId],
                $this->attributes()
            )
        );

        // Outside the write: after a rollback these must not describe a row that
        // no longer exists.
        $boundary->refresh();
        $this->boundaryId = $boundary->id;
        $this->openedAt = $this->conflictBaseline($boundary);

        return $boundary;
    }

    /**
     * Form values mapped onto column names, with blanks stored as NULL so an
     * emptied field becomes "not recorded" rather than an empty string or a
     * zero-length measurement.
     *
     * @return array<string, string|int|bool|null>
     */
    private function attributes(): array
    {
        return [
            'n_border' => $this->orNull($this->nBorder),
            's_border' => $this->orNull($this->sBorder),
            'e_border' => $this->orNull($this->eBorder),
            'w_border' => $this->orNull($this->wBorder),
            'n_dim' => $this->orNull($this->nDim),
            's_dim' => $this->orNull($this->sDim),
            'e_dim' => $this->orNull($this->eDim),
            'w_dim' => $this->orNull($this->wDim),
            'measured_area' => $this->orNull($this->measuredArea),
            'matches_deed' => match ($this->matchesDeed) {
                'yes' => true,
                'no' => false,
                default => null,
            },
            'survey_date' => $this->orNull($this->surveyDate),
            'engineering_office_id' => $this->engineeringOfficeId !== ''
                ? (int) $this->engineeringOfficeId
                : null,
        ];
    }

    private function orNull(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    /**
     * @return array<int, mixed>
     */
    private function dimensionRules(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'];
    }
}
