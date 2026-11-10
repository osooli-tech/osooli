<?php

declare(strict_types=1);

namespace App\Enums;

/** How a portal account stands to an owner it opens. */
enum PortalRelation: string
{
    /** The account is the owner's own phone. */
    case Self_ = 'self';

    /** The account acts for the owner: an office, an agent, a relative. */
    case Delegate = 'delegate';

    public function label(): string
    {
        return __('portal_accounts.relation.'.$this->value);
    }
}
