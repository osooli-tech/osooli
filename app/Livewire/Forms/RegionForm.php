<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Region;
use App\Support\Concerns\WritesSafely;
use Livewire\Form;

/**
 * A region under a country. The parent is chosen from a dropdown the screen
 * builds — the id is never typed.
 */
class RegionForm extends Form
{
    use WritesSafely;

    public ?int $regionId = null;

    /**
     * Held as a string because a <select> posts strings and its placeholder
     * option posts ''. Cast to int only on the way into the column.
     */
    public string $countryId = '';

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
            'countryId' => ['required', 'integer', 'exists:countries,id'],
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
            'countryId' => __('reference.country'),
            'nameAr' => __('reference.name_ar'),
            'nameEn' => __('reference.name_en'),
        ];
    }

    /** Load an existing region for editing, capturing the conflict baseline. */
    public function setRegion(Region $region): void
    {
        $this->regionId = $region->id;
        $this->countryId = (string) $region->country_id;
        $this->nameAr = (string) $region->name_ar;
        $this->nameEn = (string) $region->name_en;
        $this->openedAt = $this->conflictBaseline($region);
    }

    public function store(): Region
    {
        $this->validate();

        return $this->writeSafely(
            'region.create',
            'region',
            null,
            fn (): Region => Region::create($this->attributes())
        );
    }

    public function update(): Region
    {
        $this->validate();

        $region = Region::findOrFail($this->regionId);

        $this->guardAgainstConflict($region, $this->openedAt);

        return $this->writeSafely(
            'region.update',
            'region',
            $region->id,
            function () use ($region): Region {
                $region->update($this->attributes());

                return $region->refresh();
            }
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    private function attributes(): array
    {
        return [
            'country_id' => (int) $this->countryId,
            'name_ar' => $this->nameAr,
            'name_en' => $this->nameEn !== '' ? $this->nameEn : null,
        ];
    }
}
