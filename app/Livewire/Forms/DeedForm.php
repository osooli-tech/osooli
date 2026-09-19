<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Deed;
use App\Models\Parcel;
use App\Support\Concerns\WritesSafely;
use App\Support\DatabaseEnum;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * One deed of one parcel.
 *
 * A parcel carries a list of deeds, not a single current one — an updated deed
 * is issued alongside the old one rather than replacing it, which is why
 * Parcel has both latestDeed() and currentDeed(). So this form is always
 * pointed at a parcel, and either opens a specific deed by id or starts an
 * additional one for a parcel that already has some. Superseding an old deed
 * is a separate concern and no deed is removed here.
 */
class DeedForm extends Form
{
    use WritesSafely;

    public ?int $deedId = null;

    public ?int $parcelId = null;

    public string $deedNo = '';

    public string $deedDateHijri = '';

    public string $deedArea = '';

    public string $deedStatus = '';

    public string $deedClass = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Not editable from the screen: a deed belongs to the parcel it
            // was opened from. Validated anyway because the property rides in
            // the request payload like any other.
            'parcelId' => ['required', 'integer', Rule::exists('parcels', 'id')],

            // Deliberately not unique. The column carries only an index, and
            // the same number legitimately appears twice when a deed is
            // reissued — the old and the updated copy both stay on record.
            'deedNo' => ['nullable', 'string', 'max:100'],

            // Plain text in a Hijri calendar, exactly as the column stores it.
            // Laravel's `date` rule would read 1446-02-30 as Gregorian and
            // reject it, although it is a real Hijri date; the pattern checks
            // the shape and the ranges a Hijri date can take, nothing more.
            'deedDateHijri' => ['nullable', 'string', 'regex:/^1\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|30)$/'],

            // decimal(14,2): Postgres would round anything longer silently.
            'deedArea' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],

            // Blank skips every non-implicit rule, so 'nullable' alone carries
            // "not chosen"; both columns are nullable enum types.
            'deedStatus' => ['nullable', DatabaseEnum::rule('deed_status')],
            'deedClass' => ['nullable', DatabaseEnum::rule('deed_class')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // The stock "format is invalid" names neither the calendar nor the
            // order of the parts, and this column exists precisely because the
            // date is not one the framework can parse.
            'deedDateHijri.regex' => __('parcels.deed_date_invalid'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'parcelId' => __('parcels.parcel_no'),
            'deedNo' => __('parcels.deed_no'),
            'deedDateHijri' => __('parcels.deed_date'),
            'deedArea' => __('parcels.deed_area'),
            'deedStatus' => __('parcels.deed_status'),
            'deedClass' => __('parcels.deed_class'),
        ];
    }

    /** Start an additional deed for a parcel, however many it already has. */
    public function setParcel(Parcel $parcel): void
    {
        $this->deedId = null;
        $this->parcelId = $parcel->id;
        $this->deedNo = '';
        $this->deedDateHijri = '';
        $this->deedArea = '';
        $this->deedStatus = '';
        $this->deedClass = '';
        $this->openedAt = null;
    }

    /** Load one existing deed for editing, capturing the conflict baseline. */
    public function setDeed(Deed $deed): void
    {
        $this->deedId = $deed->id;
        $this->parcelId = $deed->parcel_id;
        $this->deedNo = (string) $deed->deed_no;
        $this->deedDateHijri = (string) $deed->deed_date_hijri;
        $this->deedArea = (string) $deed->deed_area;
        $this->deedStatus = (string) $deed->deed_status;
        $this->deedClass = (string) $deed->deed_class;
        $this->openedAt = $this->conflictBaseline($deed);
    }

    public function store(): Deed
    {
        $this->validate();

        return $this->writeSafely(
            'deed.create',
            'deed',
            null,
            fn (): Deed => Deed::create($this->attributes())
        );
    }

    public function update(): Deed
    {
        $this->validate();

        $deed = Deed::findOrFail($this->deedId);

        $this->guardAgainstConflict($deed, $this->openedAt);

        return $this->writeSafely(
            'deed.update',
            'deed',
            $deed->id,
            function () use ($deed): Deed {
                $deed->update($this->attributes());

                return $deed->refresh();
            }
        );
    }

    /**
     * Form values mapped onto column names, with blanks stored as NULL so an
     * emptied field does not become an empty string the enum types reject.
     *
     * @return array<string, string|int|null>
     */
    private function attributes(): array
    {
        return [
            'parcel_id' => $this->parcelId,
            'deed_no' => $this->orNull($this->deedNo),
            'deed_date_hijri' => $this->orNull($this->deedDateHijri),
            'deed_area' => $this->orNull($this->deedArea),
            'deed_status' => $this->orNull($this->deedStatus),
            'deed_class' => $this->orNull($this->deedClass),
        ];
    }

    private function orNull(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }
}
