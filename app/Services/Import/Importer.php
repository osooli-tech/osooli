<?php

declare(strict_types=1);

namespace App\Services\Import;

interface Importer
{
    /**
     * Inspect the source and report what a commit would do.
     *
     * Implementations MUST NOT write to the database. The whole
     * preview-then-confirm flow rests on this. Every implementation MUST be
     * covered by a test that compares database row counts before and after
     * calling analyze() to verify no writes occur.
     */
    public function analyze(string $sourcePath): ImportPreview;

    /** Apply the source to the database. Must be idempotent. */
    public function commit(string $sourcePath): ImportResult;
}
