<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** Serves the CMS-managed legal texts to the mobile apps. Public — the app
 * shows them before login. */
class LegalController extends ApiController
{
    public function show(string $key): JsonResponse
    {
        abort_unless(in_array($key, LegalDocument::KEYS, true), 404);

        $payload = Cache::remember(
            "legal_document_{$key}",
            300,
            function () use ($key): array {
                $document = LegalDocument::where('key', $key)->firstOrFail();

                return [
                    'key' => $document->key,
                    'title' => $document->title,
                    'content_html' => $document->content,
                    'updated_at' => $document->updated_at?->toIso8601String(),
                ];
            }
        );

        return $this->respond($payload);
    }
}
