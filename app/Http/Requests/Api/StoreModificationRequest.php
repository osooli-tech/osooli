<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModificationRequest extends FormRequest
{
    /** Fields an owner may ask to have changed. */
    public const EDITABLE_FIELDS = [
        'asset_type',
        'land_transaction',
        'allocation_method',
        'fall_in',
        'deed_status',
        'deed_class',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Ownership is enforced in the controller, not here, so an
            // unowned id reads as "not found" rather than "exists".
            'parcel_id' => ['required', 'integer'],
            'field_name' => ['required', 'string', Rule::in(self::EDITABLE_FIELDS)],
            'new_value' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
