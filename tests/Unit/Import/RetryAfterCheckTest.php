<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Jobs\Concerns\WarnsAboutLowRetryAfter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises WarnsAboutLowRetryAfter::retryAfterIsTooLow() in isolation from
 * config()/Log — those need a Laravel container, which this pure PHPUnit
 * suite (matching the rest of tests/Unit/Import) does not bootstrap. The
 * decision itself is a plain function of two values, so it is deliberately
 * factored out of the trait's config()-reading, Log-writing method to make
 * this possible — see I7 in the final review.
 */
final class RetryAfterCheckTest extends TestCase
{
    private function subject(): object
    {
        return new class
        {
            use WarnsAboutLowRetryAfter;
        };
    }

    public function test_it_flags_a_retry_after_below_the_timeout(): void
    {
        // config/queue.php's own default (90s) against CommitImportBatch's
        // real timeout (1800s) — exactly the unpatched-.env scenario I7
        // describes.
        $this->assertTrue($this->subject()::retryAfterIsTooLow(90, 1800));
    }

    public function test_it_accepts_a_retry_after_above_the_timeout(): void
    {
        // .env.example's own documented value.
        $this->assertFalse($this->subject()::retryAfterIsTooLow(1900, 1800));
    }

    public function test_it_accepts_a_retry_after_exactly_equal_to_the_timeout(): void
    {
        // Laravel's documented rule is "must exceed", not "must at least
        // equal", so a connection with zero headroom is still a
        // configuration risk — but this check only needs to catch the
        // clearly-wrong case (a default far below every job's timeout), so
        // equal is accepted rather than flagged.
        $this->assertFalse($this->subject()::retryAfterIsTooLow(1800, 1800));
    }

    public function test_it_ignores_a_connection_with_no_retry_after_key_at_all(): void
    {
        // The 'sync' connection in config/queue.php has no retry_after key,
        // so config() returns null for it — this must never read as "too
        // low".
        $this->assertFalse($this->subject()::retryAfterIsTooLow(null, 1800));
    }
}
