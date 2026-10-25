<?php

declare(strict_types=1);

namespace App\Http\Requests\Listings;

use App\Services\Listings\ParcelListingService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An owner offering one of their parcels for sale, from the portal or the
 * mobile app. The parcel comes from the route and is checked against the
 * owner's own holdings in the controller.
 */
class StoreListingRequest extends FormRequest
{
    /** A Saudi mobile number, with or without the country code. */
    public const PHONE_RULE = 'regex:/^(?:\+?966|0)?5\d{8}$/';

    /** @var string */
    protected $errorBag = ParcelListingService::ERROR_BAG;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['contact_phone' => preg_replace('/[\s-]+/', '', (string) $this->input('contact_phone'))]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'contact_phone' => ['required', 'string', self::PHONE_RULE],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['contact_phone' => __('listings.contact_phone')];
    }
}
