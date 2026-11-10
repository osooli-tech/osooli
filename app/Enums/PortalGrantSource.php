<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a portal grant came from. */
enum PortalGrantSource: string
{
    /** Built from owners' phones when portal accounts were introduced. */
    case Migration = 'migration';

    /** A phone read from an import file. */
    case Import = 'import';

    /** Given by a member of staff. */
    case Staff = 'staff';
}
