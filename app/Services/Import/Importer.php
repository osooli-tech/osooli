<?php

declare(strict_types=1);

namespace App\Services\Import;

interface Importer
{
    /**
     * Inspect the source and report what a commit would do.
     *
     * Implementations MUST NOT write to the database. The whole
     * preview-then-confirm flow rests on this, and the test suite asserts it
     * by comparing row counts before and after.
     */
    public function analyze(string $sourcePath): ImportPreview;

    /** Apply the source to the database. Must be idempotent. */
    public function commit(string $sourcePath): ImportResult;
}
