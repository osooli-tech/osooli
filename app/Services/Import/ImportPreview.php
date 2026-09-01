<?php

declare(strict_types=1);

namespace App\Services\Import;

/** What an analyze() pass found. Never reflects a write. */
final readonly class ImportPreview
{
    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $totalItems,
        public int $willCreate,
        public int $willUpdate,
        public int $unmatched,
        public array $details = [],
        public array $warnings = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'total_items' => $this->totalItems,
            'will_create' => $this->willCreate,
            'will_update' => $this->willUpdate,
            'unmatched' => $this->unmatched,
            'details' => $this->details,
            'warnings' => $this->warnings,
        ];
    }
}
