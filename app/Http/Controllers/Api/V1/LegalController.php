<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Serves the CMS-managed legal texts to the mobile apps. Public — the app shows
 * them before login.
 *
 * The language follows Accept-Language, so the app gets the document in the
 * device's language without asking for it. English falls back to Arabic while
 * no translation exists, and the response says which one it actually carries.
 */
class LegalController extends ApiController
{
    public function show(string $key): JsonResponse
    {
        abort_unless(in_array($key, LegalDocument::KEYS, true), 404);

        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';

        $payload = Cache::remember(
            "legal_document_{$key}_{$locale}",
            300,
            function () use ($key, $locale): array {
                $document = LegalDocument::where('key', $key)->firstOrFail();

                return [
                    'key' => $document->key,
                    'locale' => $document->hasTranslation($locale) ? $locale : 'ar',
                    'title' => $document->titleFor($locale),
                    'content_html' => $document->contentFor($locale),
                    'updated_at' => $document->updated_at?->toIso8601String(),
                ];
            }
        );

        return $this->respond($payload);
    }
}
