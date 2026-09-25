<?php

declare(strict_types=1);

namespace App\Livewire\Archive;

use App\Livewire\Concerns\FiltersByCreatedAt;
use App\Models\Deed;
use App\Models\Owner;
use App\Models\Parcel;
use App\Support\Concerns\WritesSafely;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The archive: everything taken out of circulation, and the way back.
 *
 * Without a screen like this, archiving is just a slower delete — the row
 * survives in the database but nobody can reach it, which is the worst of both
 * designs. Restoring is the whole point, so it lives one click from the list.
 */
class ArchiveIndex extends Component
{
    use FiltersByCreatedAt;
    use WritesSafely;

    /** The three archivable record types, in the order they are shown. */
    private const TYPES = ['parcels', 'deeds', 'owners'];

    public string $tab = 'parcels';

    public string $search = '';

    #[Locked]
    public ?int $restoringId = null;

    public function updatingSearch(): void
    {
        $this->restoringId = null;
    }

    public function switchTab(string $tab): void
    {
        // Whitelisted: $tab arrives from the client and picks the model class.
        if (in_array($tab, self::TYPES, true)) {
            $this->tab = $tab;
            $this->search = '';
            $this->restoringId = null;
        }
    }

    public function confirmRestore(int $id): void
    {
        $this->restoringId = $id;
    }

    public function cancelRestore(): void
    {
        $this->restoringId = null;
    }

    public function restore(int $id): void
    {
        abort_unless(auth()->user()?->can('archive.restore'), 403);

        $record = $this->query()->whereKey($id)->firstOrFail();

        $this->writeSafely(
            $this->tab.'.restore',
            rtrim($this->tab, 's'),
            $id,
            function () use ($record): Model {
                /** @var Parcel|Deed|Owner $record */
                $record->unarchive();

                return $record;
            }
        );

        $this->restoringId = null;
        $this->dispatch('toast', type: 'success', message: __('common.restored'));
    }

    /**
     * Archived records of the current tab.
     *
     * Three separate builder types rather than one over a union: each match
     * arm returns a builder bound to its own model, which is not the same as
     * a single builder that could yield any of the three.
     *
     * @return Builder<Parcel>|Builder<Deed>|Builder<Owner>
     */
    private function query()
    {
        $builder = match ($this->tab) {
            'deeds' => Deed::query()->onlyTrashed()->with('parcel'),
            'owners' => Owner::query()->onlyTrashed(),
            default => Parcel::query()->onlyTrashed()->with('plan'),
        };

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';

            $builder->where(function ($q) use ($term): void {
                match ($this->tab) {
                    'deeds' => $q->whereLike('deed_no', $term),
                    'owners' => $q->whereLike('name', $term)
                        ->orWhereLike('national_id', $term),
                    default => $q->whereLike('parcel_no', $term)
                        ->orWhereLike('geo_id', $term),
                };
            });
        }

        return $this->applyCreatedAt($builder->with('archivedBy')->latest('deleted_at'));
    }

    /** @return Collection<int, Parcel>|Collection<int, Deed>|Collection<int, Owner> */
    public function records(): Collection
    {
        return $this->query()->limit(100)->get();
    }

    /**
     * How many archived records each tab holds, so an empty tab is visibly
     * empty rather than looking like a page that failed to load.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'parcels' => Parcel::query()->onlyTrashed()->count(),
            'deeds' => Deed::query()->onlyTrashed()->count(),
            'owners' => Owner::query()->onlyTrashed()->count(),
        ];
    }

    public function render(): View
    {
        $user = auth()->user();

        abort_unless($user?->can('archive.view'), 403);

        return view('livewire.archive.archive-index', [
            'records' => $this->records(),
            'counts' => $this->counts(),
            'canRestore' => $user->can('archive.restore'),
        ]);
    }
}
