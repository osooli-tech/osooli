<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\ReportLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ReportLocaleTest extends TestCase
{
    public function test_the_chosen_language_applies_to_the_report(): void
    {
        app()->setLocale('ar');

        $seen = $this->handle('/owners/1/print?lang=en');

        $this->assertSame('en', $seen);
    }

    public function test_an_unknown_or_missing_language_leaves_the_locale_alone(): void
    {
        app()->setLocale('ar');

        $this->assertSame('ar', $this->handle('/owners/1/print?lang=fr'));
        $this->assertSame('ar', $this->handle('/owners/1/print'));
    }

    /** The locale the report would be rendered in. */
    private function handle(string $uri): string
    {
        $seen = '';

        (new ReportLocale)->handle(Request::create($uri), function () use (&$seen): Response {
            $seen = app()->getLocale();

            return new Response;
        });

        return $seen;
    }
}
