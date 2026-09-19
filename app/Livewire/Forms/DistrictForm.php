<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\District;
use App\Support\Concerns\WritesSafely;
use Livewire\Form;

/**
 * A district under a city. The parent is chosen from a dropdown the screen
 * builds — the id is never typed.
 */
class DistrictForm extends Form
{
    use WritesSafely;

    public ?int $districtId = null;

    /**
     * Held as a string because a <select> posts strings and its placeholder
     * option posts ''. Cast to int only on the way into the column.
     */
    public string $cityId = '';

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
            'cityId' => ['required', 'integer', 'exists:cities,id'],
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
            'cityId' => __('reference.city'),
            'nameAr' => __('reference.name_ar'),
            'nameEn' => __('reference.name_en'),
        ];
    }

    /** Load an existing district for editing, capturing the conflict baseline. */
    public function setDistrict(District $district): void
    {
        $this->districtId = $district->id;
        $this->cityId = (string) $district->city_id;
        $this->nameAr = (string) $district->name_ar;
        $this->nameEn = (string) $district->name_en;
        $this->openedAt = $this->conflictBaseline($district);
    }

    public function store(): District
    {
        $this->validate();

        return $this->writeSafely(
            'district.create',
            'district',
            null,
            fn (): District => District::create($this->attributes())
        );
    }

    public function update(): District
    {
        $this->validate();

        $district = District::findOrFail($this->districtId);

        $this->guardAgainstConflict($district, $this->openedAt);

        return $this->writeSafely(
            'district.update',
            'district',
            $district->id,
            function () use ($district): District {
                $district->update($this->attributes());

                return $district->refresh();
            }
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    private function attributes(): array
    {
        return [
            'city_id' => (int) $this->cityId,
            'name_ar' => $this->nameAr,
            'name_en' => $this->nameEn !== '' ? $this->nameEn : null,
        ];
    }
}
