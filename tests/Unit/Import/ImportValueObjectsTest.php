<?php

declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Services\Import\ImportPreview;
use App\Services\Import\ImportResult;
use PHPUnit\Framework\TestCase;

final class ImportValueObjectsTest extends TestCase
{
    public function test_preview_serialises_every_counter(): void
    {
        $preview = new ImportPreview(
            totalItems: 168,
            willCreate: 12,
            willUpdate: 156,
            unmatched: 3,
            details: ['layer' => 'sakoki_with_deed'],
            warnings: ['plan 20A is not numeric'],
        );

        $this->assertSame([
            'total_items' => 168,
            'will_create' => 12,
            'will_update' => 156,
            'unmatched' => 3,
            'details' => ['layer' => 'sakoki_with_deed'],
            'warnings' => ['plan 20A is not numeric'],
        ], $preview->toArray());
    }

    public function test_result_serialises_every_counter(): void
    {
        $result = new ImportResult(
            created: 12,
            updated: 156,
            skipped: 3,
            errors: 1,
            details: ['deeds' => 165],
            warnings: [],
        );

        $this->assertSame([
            'created' => 12,
            'updated' => 156,
            'skipped' => 3,
            'errors' => 1,
            'details' => ['deeds' => 165],
            'warnings' => [],
        ], $result->toArray());
    }
}
