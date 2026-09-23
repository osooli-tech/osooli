<?php

declare(strict_types=1);

namespace App\Livewire\Owners;

use App\Livewire\Forms\OwnershipForm;
use App\Models\DeedOwner;
use App\Models\Owner;
use App\Support\Concerns\WritesSafely;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use PDOException;
use RuntimeException;

/**
 * Who owns one deed, and in what share.
 *
 * The share is shown in four places across the dashboard and was writable from
 * none of them; this is the one screen that writes it. It links an existing
 * owner, edits a share and unlinks — it never creates or deletes owners.
 */
class OwnershipManager extends Component
{
    use WritesSafely;

    /** How many owners the picker offers before it asks for a narrower search. */
    private const PICKER_LIMIT = 25;

    #[Locked]
    public int $deedId;

    public OwnershipForm $form;

    public bool $editing = false;

    public string $ownerSearch = '';

    public function mount(int $deedId): void
    {
        // The blade including this component sits behind @can('ownership.manage'),
        // which hides the markup but does not stop a request aimed straight at
        // these actions — so every entry point checks for itself.
        abort_unless(auth()->user()?->can('ownership.manage'), 403);

        $this->deedId = $deedId;
    }

    /** Runs after mount and after every hydrate. */
    public function booted(): void
    {
        // The form's state arrives from the browser on each request; the deed
        // it writes to comes from the locked property, never from that payload.
        $this->form->deedId = $this->deedId;
    }

    public function add(): void
    {
        $this->resetValidation();
        $this->form->resetExcept('deedId');
        $this->editing = true;
    }

    public function edit(int $deedOwnerId): void
    {
        abort_unless(auth()->user()?->can('ownership.manage'), 403);

        $this->resetValidation();
        $this->form->setLink($this->link($deedOwnerId));
        $this->editing = true;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->form->resetExcept('deedId');
        $this->ownerSearch = '';
        $this->editing = false;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('ownership.manage'), 403);

        try {
            if ($this->form->deedOwnerId === null) {
                $this->form->store();
            } else {
                $this->form->update();
            }
        } catch (UniqueConstraintViolationException) {
            // Validation already checks the pair, so arriving here means another
            // request linked the same owner in between. Say it in the validator's
            // words rather than letting a driver message reach the screen.
            $this->addError('form.ownerId', __('owners.ownership_duplicate'));

            return;
        } catch (PDOException $e) {
            // Any other database fault is a real fault and must not be dressed
            // up as a form error. Caught before the clause below because
            // PDOException is itself a RuntimeException.
            throw $e;
        } catch (RuntimeException $e) {
            // The optimistic lock refusing a stale save: a normal outcome that
            // carries its own translated explanation, not a 500.
            $this->addError('form.conflict', $e->getMessage());

            return;
        }

        $this->cancel();
        $this->dispatch('ownership-updated');
    }

    public function unlink(int $deedOwnerId): void
    {
        abort_unless(auth()->user()?->can('ownership.manage'), 403);

        $link = $this->link($deedOwnerId);

        $this->writeSafely('deed_owner.delete', 'deed_owner', $link->id, function () use ($link): bool {
            // Removes the link only, never the owner: deed_owners.owner_id is
            // restrictOnDelete so an owner's history cannot be erased here.
            return (bool) $link->delete();
        });

        if ($this->form->deedOwnerId === $deedOwnerId) {
            $this->cancel();
        }

        $this->dispatch('ownership-updated');
    }

    public function render(): View
    {
        $links = $this->links();
        $allocated = $this->allocated($links);

        return view('livewire.owners.ownership-manager', [
            'links' => $links,
            'allocated' => $allocated,
            'remaining' => max(0.0, OwnershipForm::CEILING - $allocated),
            'ownerOptions' => $this->ownerOptions(),
            'pickerLimit' => self::PICKER_LIMIT,
        ]);
    }

    /** @return Collection<int, DeedOwner> */
    private function links(): Collection
    {
        return DeedOwner::query()
            ->with('owner')
            ->where('deed_id', $this->deedId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Percentage already committed on this deed.
     *
     * @param  Collection<int, DeedOwner>  $links
     */
    private function allocated(Collection $links): float
    {
        $total = 0.0;

        foreach ($links as $link) {
            // NULL means the share is written as prose in the deed, not zero;
            // it adds nothing here instead of consuming the remainder.
            $total += (float) ($link->ownership_share ?? 0);
        }

        return round($total, 2);
    }

    /**
     * The picker's list: owners matching the search box, minus the ones already
     * on this deed, always including whichever owner is currently selected.
     *
     * Staff pick a person, never an id — the id only ever travels as the value
     * of the option they chose.
     *
     * @return array<int, string> owner id => "name — national id"
     */
    private function ownerOptions(): array
    {
        $linkedQuery = DeedOwner::query()->where('deed_id', $this->deedId);

        if ($this->form->deedOwnerId !== null) {
            $linkedQuery->whereKeyNot($this->form->deedOwnerId);
        }

        // Owners already on the deed are hidden rather than offered and then
        // rejected; the unique rule still guards the write for a stale page.
        $owners = Owner::query()->whereNotIn('id', $linkedQuery->pluck('owner_id'));

        $term = trim($this->ownerSearch);

        if ($term !== '') {
            $like = '%'.$term.'%';

            // The same three columns the owners list searches, so an owner is
            // found the same way on both screens.
            $owners->where(function (Builder $inner) use ($like): void {
                $inner->whereLike('name', $like)
                    ->orWhereLike('national_id', $like)
                    ->orWhereLike('phone', $like);
            });
        }

        $matches = $owners->orderBy('name')->limit(self::PICKER_LIMIT)->get(['id', 'name', 'national_id']);

        $selectedId = $this->form->ownerId === '' ? null : (int) $this->form->ownerId;

        if ($selectedId !== null && ! $matches->contains('id', $selectedId)) {
            // Keep the row's own owner selectable even when the search term or
            // the limit would drop it, or reopening an edit blanks the field.
            $selected = Owner::query()->find($selectedId, ['id', 'name', 'national_id']);

            if ($selected !== null) {
                $matches = $matches->prepend($selected);
            }
        }

        return $matches->mapWithKeys(fn (Owner $owner): array => [
            $owner->id => $owner->national_id !== null && $owner->national_id !== ''
                ? $owner->name.' — '.$owner->national_id
                : $owner->name,
        ])->all();
    }

    /**
     * One of this deed's ownership rows by id, scoped to the locked deed so a
     * tampered payload cannot reach a row belonging to another deed.
     */
    private function link(int $deedOwnerId): DeedOwner
    {
        return DeedOwner::query()
            ->where('deed_id', $this->deedId)
            ->findOrFail($deedOwnerId);
    }
}
