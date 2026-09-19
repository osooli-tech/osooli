<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Country;
use App\Support\Concerns\WritesSafely;
use Livewire\Form;

/**
 * Top of the location chain: country → region → city → district → plan.
 *
 * Nothing sits above a country, so this is the one reference form without a
 * parent select. Shaped after OwnerForm so every reference screen reads the
 * same way.
 */
class CountryForm extends Form
{
    use WritesSafely;

    public ?int $countryId = null;

    public string $nameAr = '';

    public string $nameEn = '';

    public string $isoCode = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nameAr' => ['required', 'string', 'max:150'],
            'nameEn' => ['nullable', 'string', 'max:150'],
            'isoCode' => ['nullable', 'string', 'max:5'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'nameAr' => __('reference.name_ar'),
            'nameEn' => __('reference.name_en'),
            'isoCode' => __('reference.iso_code'),
        ];
    }

    /** Load an existing country for editing, capturing the conflict baseline. */
    public function setCountry(Country $country): void
    {
        $this->countryId = $country->id;
        $this->nameAr = (string) $country->name_ar;
        $this->nameEn = (string) $country->name_en;
        $this->isoCode = (string) $country->iso_code;
        $this->openedAt = $this->conflictBaseline($country);
    }

    public function store(): Country
    {
        $this->validate();

        return $this->writeSafely(
            'country.create',
            'country',
            null,
            fn (): Country => Country::create($this->attributes())
        );
    }

    public function update(): Country
    {
        $this->validate();

        $country = Country::findOrFail($this->countryId);

        $this->guardAgainstConflict($country, $this->openedAt);

        return $this->writeSafely(
            'country.update',
            'country',
            $country->id,
            function () use ($country): Country {
                $country->update($this->attributes());

                return $country->refresh();
            }
        );
    }

    /**
     * Form values mapped onto column names, with blanks stored as NULL so an
     * emptied optional field does not become an empty string.
     *
     * @return array<string, string|null>
     */
    private function attributes(): array
    {
        return [
            'name_ar' => $this->nameAr,
            'name_en' => $this->nameEn !== '' ? $this->nameEn : null,
            'iso_code' => $this->isoCode !== '' ? $this->isoCode : null,
        ];
    }
}
