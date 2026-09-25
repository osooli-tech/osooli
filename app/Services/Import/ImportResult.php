<?php

declare(strict_types=1);

namespace App\Services\Import;

/** What a commit() pass actually did. */
final readonly class ImportResult
{
    /**
     * @param  array<string, mixed>  $details
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public int $errors,
        public array $details = [],
        public array $warnings = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'details' => $this->details,
            'warnings' => $this->warnings,
        ];
    }
}
