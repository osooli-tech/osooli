<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Http\Requests\Api\StoreModificationRequest as ApiStoreModificationRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The parcel comes from the route and is checked against the owner's own holdings in the controller. */
class StoreModificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'field_name' => ['required', 'string', Rule::in(ApiStoreModificationRequest::EDITABLE_FIELDS)],
            'new_value' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
