<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Forward-only import lifecycle.
 *
 * The rule that matters: only Previewed may move to Committing. That is what
 * stops a replayed request from writing twice and stops anything committing
 * without having been analysed first.
 */
enum ImportStatus: string
{
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Analyzing = 'analyzing';
    case Previewed = 'previewed';
    case Committing = 'committing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** @return list<self> */
    private function allowedNext(): array
    {
        return match ($this) {
            self::Uploading => [self::Uploaded, self::Failed],
            self::Uploaded => [self::Analyzing, self::Failed],
            self::Analyzing => [self::Previewed, self::Failed],
            self::Previewed => [self::Committing, self::Failed],
            self::Committing => [self::Completed, self::Failed],
            self::Completed, self::Failed => [],
        };
    }
}
