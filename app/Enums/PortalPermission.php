<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a portal account may do for one owner, set by staff on the grant.
 * Everything is allowed until staff turn something off — so an owner who does
 * not want their agent selling can say so without losing the rest.
 */
enum PortalPermission: string
{
    case Sell = 'sell';
    case RequestChanges = 'request_changes';
    case UploadPhotos = 'upload_photos';
    case Documents = 'documents';

    /** Acting needs the grant's can_manage as well; looking at documents does not. */
    public function needsManage(): bool
    {
        return $this !== self::Documents;
    }

    public function label(): string
    {
        return __('portal_accounts.permission.'.$this->value);
    }
}
