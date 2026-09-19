<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\City;
use App\Support\Concerns\WritesSafely;
use Livewire\Form;

/**
 * A city under a region. The parent is chosen from a dropdown the screen
 * builds — the id is never typed.
 */
class CityForm extends Form
{
    use WritesSafely;

    public ?int $cityId = null;

    /**
     * Held as a string because a <select> posts strings and its placeholder
     * option posts ''. Cast to int only on the way into the column.
     */
    public string $regionId = '';

    public string $nameAr = '';

    public string $nameEn = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'regionId' => ['required', 'integer', 'exists:regions,id'],
            'nameAr' => ['required', 'string', 'max:150'],
            'nameEn' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'regionId' => __('reference.region'),
            'nameAr' => __('reference.name_ar'),
            'nameEn' => __('reference.name_en'),
        ];
    }

    /** Load an existing city for editing, capturing the conflict baseline. */
    public function setCity(City $city): void
    {
        $this->cityId = $city->id;
        $this->regionId = (string) $city->region_id;
        $this->nameAr = (string) $city->name_ar;
        $this->nameEn = (string) $city->name_en;
        $this->openedAt = $this->conflictBaseline($city);
    }

    public function store(): City
    {
        $this->validate();

        return $this->writeSafely(
            'city.create',
            'city',
            null,
            fn (): City => City::create($this->attributes())
        );
    }

    public function update(): City
    {
        $this->validate();

        $city = City::findOrFail($this->cityId);

        $this->guardAgainstConflict($city, $this->openedAt);

        return $this->writeSafely(
            'city.update',
            'city',
            $city->id,
            function () use ($city): City {
                $city->update($this->attributes());

                return $city->refresh();
            }
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    private function attributes(): array
    {
        return [
            'region_id' => (int) $this->regionId,
            'name_ar' => $this->nameAr,
            'name_en' => $this->nameEn !== '' ? $this->nameEn : null,
        ];
    }
}
