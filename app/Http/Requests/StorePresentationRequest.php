<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            // Accepts 05XXXXXXXX, 5XXXXXXXX, +9665XXXXXXXX and 9665XXXXXXXX.
            'phone' => ['required', 'string', 'regex:/^(?:\+?966|0)?5\d{8}$/'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
