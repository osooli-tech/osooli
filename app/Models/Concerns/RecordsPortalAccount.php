<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Portal\PortalContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Stamps a record an owner creates in the portal or the app with the portal
 * account that actually did it (portal_account_id), beside the owner it was
 * done for. Records made by staff, imports or the console carry none.
 */
trait RecordsPortalAccount
{
    protected static function bootRecordsPortalAccount(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('portal_account_id') !== null) {
                return;
            }

            $context = app(PortalContext::class);

            if ($context->owner() !== null) {
                $model->setAttribute('portal_account_id', $context->account()?->id);
            }
        });
    }
}
