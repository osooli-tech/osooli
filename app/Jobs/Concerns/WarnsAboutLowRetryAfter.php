<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Detects the case docs/import-runbook.md §2.3 and §9 only ever documented,
 * never checked for: a queue connection's retry_after lower than this job's
 * own $timeout marks a still-running job as abandoned, redelivers it to
 * another worker, exhausts max attempts, and calls failed() on a job that in
 * fact finishes successfully moments later — a FALSE FAILURE with a missing
 * result (the original worker's transitionTo(Completed) then loses its
 * compare-and-swap against the failed() call's transitionTo(Failed)), not
 * merely "duplicate-looking activity". The double-write guard in
 * ImportBatch::transitionTo() is unaffected either way — this is purely an
 * audit/reporting problem.
 *
 * This only logs. The job itself has no way to fix a misconfigured .env, and
 * refusing to run would make a correctly configured production deploy (the
 * common case) collateral damage of a check aimed at a misconfigured one.
 */
trait WarnsAboutLowRetryAfter
{
    private function warnIfRetryAfterIsTooLow(): void
    {
        $connection = (string) config('queue.default');
        $retryAfter = config("queue.connections.{$connection}.retry_after");

        if (! self::retryAfterIsTooLow($retryAfter, $this->timeout)) {
            return;
        }

        Log::warning(
            'Queue retry_after is lower than this job\'s timeout — a still-running job can be '
            .'redelivered and falsely reported as failed with no result recorded. Set '
            .'DB_QUEUE_RETRY_AFTER (or the equivalent env var for your queue connection) above '
            .'the timeout.',
            [
                'job' => static::class,
                'connection' => $connection,
                'timeout' => $this->timeout,
                'retry_after' => $retryAfter,
            ]
        );
    }

    /**
     * The decision itself, isolated from config()/Log so it can be
     * unit-tested without a Laravel container — see
     * tests/Unit/Import/RetryAfterCheckTest.php. A connection with no
     * retry_after key at all (the 'sync' driver, for one) reads as null here,
     * which must never be treated as "too low".
     */
    public static function retryAfterIsTooLow(mixed $retryAfter, int $timeout): bool
    {
        return is_int($retryAfter) && $retryAfter < $timeout;
    }
}
