<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Owner;
use App\Models\Parcel;
use Illuminate\Console\Command;

/**
 * Names an owner the parent owner of every parcel that used to be in their
 * name and is now held by someone else — the land they handed on. After it,
 * those parcels count in the owner's holdings again and appear as portfolios
 * under the people who hold them.
 *
 * Only parcels with no parent owner yet are touched, so a choice a person
 * made by hand is never overwritten and the command can be run again.
 */
class LinkHandedParcels extends Command
{
    protected $signature = 'owners:link-handed-parcels {owner : The owner id} {--dry-run : List the parcels without changing them}';

    protected $description = 'Set an owner as parent owner of the parcels they once held and others hold now';

    public function handle(): int
    {
        $owner = Owner::find((int) $this->argument('owner'));

        if ($owner === null) {
            $this->error('No owner with that id.');

            return self::FAILURE;
        }

        $handed = Parcel::query()
            ->whereNull('parent_owner_id')
            // Once on a deed of the parcel…
            ->whereHas('deeds.owners', fn ($q) => $q->whereKey($owner->getKey()))
            // …and not the one holding it now.
            ->whereNotIn('parcels.id', $owner->parcels()->select('parcels.id'))
            ->with('heldDeed.owners')
            ->orderBy('parcel_no')
            ->get();

        $this->table(['Parcel', 'Held now by'], $handed->map(fn (Parcel $parcel): array => [
            (string) $parcel->parcel_no,
            $parcel->heldDeed?->owners->pluck('name')->implode('، ') ?: '—',
        ])->all());

        if (! $this->option('dry-run')) {
            Parcel::whereKey($handed->modelKeys())->update(['parent_owner_id' => $owner->getKey()]);
        }

        $own = $owner->parcels()->count();
        $verb = $this->option('dry-run') ? 'would be' : 'were';
        $this->info("{$handed->count()} parcels {$verb} linked to {$owner->name}: {$own} held + ".$owner->linkedParcels()->count().' linked now.');

        return self::SUCCESS;
    }
}
