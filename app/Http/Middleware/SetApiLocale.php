<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the response language for the mobile API from the Accept-Language
 * header.
 *
 * The dashboard keeps its own SetLocale, which reads the session. An API
 * request carries no session, so the two cannot share one middleware.
 */
class SetApiLocale
{
    /** Languages the API ships translations for. */
    private const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        // getLanguages() is already sorted by the header's q-values, so the
        // first supported entry is the client's strongest preference.
        foreach ($request->getLanguages() as $language) {
            $base = strtolower(substr($language, 0, 2));

            if (in_array($base, self::SUPPORTED, true)) {
                app()->setLocale($base);
                break;
            }
        }

        // No header, or one naming only unsupported languages, leaves the
        // application default in place.
        return $next($request);
    }
}
