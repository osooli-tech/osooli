<?php

declare(strict_types=1);

namespace App\Enums;

enum ModificationRequestStatus: string
{
    case Pending = 'pending';
    case SentToArcgis = 'sent_to_arcgis';
    case Applied = 'applied';
    case Rejected = 'rejected';

    /** Status values that count as "resolved" (set resolved_at) */
    public function isResolved(): bool
    {
        return match ($this) {
            self::Applied, self::Rejected => true,
            default => false,
        };
    }

    /** Which transitions are allowed FROM this status */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::SentToArcgis, self::Rejected],
            self::SentToArcgis => [self::Applied, self::Rejected],
            self::Applied, self::Rejected => [],
        };
    }
}
