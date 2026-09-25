<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language of one printed report, chosen in the pop-up that opens when
 * the print button is pressed (?lang=ar|en). It applies to this request
 * only: printing an English report does not switch the dashboard to English.
 */
class ReportLocale
{
    private const LANGUAGES = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $language = (string) $request->query('lang', '');

        if (in_array($language, self::LANGUAGES, true)) {
            app()->setLocale($language);
        }

        return $next($request);
    }
}
