<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a buyer's request for Sukooki to put them in touch with a seller stands. */
enum InquiryStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';

    public function label(): string
    {
        return __('listings.inquiry_status.'.$this->value);
    }
}
