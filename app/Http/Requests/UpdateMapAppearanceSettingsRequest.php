<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\MapAppearanceSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMapAppearanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.manage') ?? false;
    }

    /**
     * The map is shared by every viewer, so only mode/value keys this app
     * actually knows about may be stored — an unrecognised key would just be
     * dead weight in the JSON column, never read back by anything.
     */
    protected function prepareForValidation(): void
    {
        $colourModes = $this->input('colour_modes');

        if (! is_array($colourModes)) {
            return;
        }

        $known = MapAppearanceSetting::DEFAULTS['colour_modes'];
        $filtered = [];

        foreach ($colourModes as $mode => $values) {
            if (! isset($known[$mode]) || ! is_array($values)) {
                continue;
            }

            foreach ($values as $value => $colour) {
                if (array_key_exists($value, $known[$mode])) {
                    $filtered[$mode][$value] = $colour;
                }
            }
        }

        $this->merge(['colour_modes' => $filtered]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';

        return [
            'parcels_fill' => ['sometimes', $hex],
            'parcels_outline' => ['sometimes', $hex],
            'projects_fill' => ['sometimes', $hex],
            'buildings_fill' => ['sometimes', $hex],
            'colour_modes' => ['sometimes', 'array'],
            'colour_modes.*' => ['array'],
            'colour_modes.*.*' => [$hex],
        ];
    }
}
