<?php

declare(strict_types=1);

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;

/** A buyer asking Sukooki to put them in touch with a listing's seller; the listing comes from the route. */
class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['note' => __('listings.interest_note')];
    }
}
