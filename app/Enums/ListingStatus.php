<?php

declare(strict_types=1);

namespace App\Enums;

/** Where an owner's offer to sell a parcel stands. Only an approved one is shown to other owners. */
enum ListingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** A parcel may carry one open listing at a time. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::Withdrawn],
            self::Approved => [self::Withdrawn],
            self::Rejected, self::Withdrawn => [],
        };
    }

    public function label(): string
    {
        return __('listings.status.'.$this->value);
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Pending->value, self::Approved->value];
    }
}
