<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use App\Enums\PhotoType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An owner adding photos of their own parcel, from the portal or the app. The
 * parcel comes from the route and is checked against the owner's holdings in
 * the controller.
 */
class StoreParcelPhotosRequest extends FormRequest
{
    public const MAX_FILES = 10;

    /** Kilobytes, as Laravel's max rule counts them. */
    public const MAX_KB = 10240;

    /** @var string */
    protected $errorBag = 'photos';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KB],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['photos' => __('portal.photos_field'), 'photos.*' => __('portal.photos_field')];
    }

    /**
     * Always a ground photo: the other gallery type, جوية, stands for the
     * agricultural sheet in this project — not something an owner photographs.
     */
    public function photoType(): PhotoType
    {
        return PhotoType::Ground;
    }
}
