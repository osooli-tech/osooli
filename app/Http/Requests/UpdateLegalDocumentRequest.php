<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.manage') ?? false;
    }

    /**
     * An emptied Quill editor posts markup, not an empty string — typically
     * "<p></p>". Left alone it would count as a real translation and serve a
     * blank page, so markup carrying no text is normalised to null first.
     */
    protected function prepareForValidation(): void
    {
        $normalised = [];

        foreach (['title_ar', 'title_en', 'content_ar', 'content_en'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));
            $normalised[$field] = $text === '' ? null : $value;
        }

        $this->merge($normalised);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:120'],
            'content_ar' => ['required', 'string', 'max:100000'],
            // English may be left empty; readers fall back to Arabic.
            'title_en' => ['nullable', 'string', 'max:120'],
            'content_en' => ['nullable', 'string', 'max:100000'],
        ];
    }
}
