<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Deed;
use App\Models\DeedOwner;
use App\Support\Concerns\WritesSafely;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;
use RuntimeException;

/**
 * One owner's share of one deed — the `deed_owners` row.
 *
 * Ownership hangs off the deed, never off the parcel: a deed can carry several
 * co-owners, and an owner can appear on many deeds. The deed is therefore
 * context the component supplies, not a field the user picks.
 *
 * Modelled on OwnerForm: rules, Arabic labels and the write live together so
 * the link screen and the edit screen cannot drift apart.
 */
class OwnershipForm extends Form
{
    use WritesSafely;

    /** The shares recorded against one deed may never add up to more than this. */
    public const CEILING = 100.0;

    /** Set by the component from its locked property, never from the request payload. */
    public ?int $deedId = null;

    /** The `deed_owners` row being edited; null while linking a new owner. */
    public ?int $deedOwnerId = null;

    /** Owner id as the picker returns it — a string, because it comes from a <select>. */
    public string $ownerId = '';

    /** Empty means NULL: the share is written as prose inside the deed document. */
    public string $share = '';

    /** `updated_at` as it stood when the row was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The table's UNIQUE(deed_id, owner_id) reaches the user as a
            // validation message here instead of as a driver exception.
            'ownerId' => [
                'required', 'integer', 'exists:owners,id',
                Rule::unique('deed_owners', 'owner_id')
                    ->where('deed_id', $this->deedId())
                    ->ignore($this->deedOwnerId),
            ],

            // Nullable on purpose: a deed that states its shares in prose has
            // no percentage to record, and that is not the same as zero.
            // `decimal:0,2` mirrors the column's decimal(5,2); `bail` keeps a
            // single typo from producing three stacked complaints.
            'share' => ['bail', 'nullable', 'numeric', 'decimal:0,2', 'between:0,100'],
        ];
    }

    /**
     * Every rule this form can fail, worded in Arabic.
     *
     * The framework's own messages are English — the app ships no
     * lang/ar/validation.php — so each one is spelled out here rather than
     * letting an English sentence surface in an Arabic screen.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ownerId.required' => __('owners.ownership_owner_required'),
            'ownerId.integer' => __('owners.ownership_owner_invalid'),
            'ownerId.exists' => __('owners.ownership_owner_invalid'),
            'ownerId.unique' => __('owners.ownership_duplicate'),
            'share.numeric' => __('owners.ownership_share_numeric'),
            'share.decimal' => __('owners.ownership_share_decimals'),
            'share.between' => __('owners.ownership_share_range'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'ownerId' => __('owners.owner'),
            'share' => __('owners.ownership_share'),
        ];
    }

    /** Load an existing link for editing, capturing the conflict baseline. */
    public function setLink(DeedOwner $link): void
    {
        $this->deedOwnerId = $link->id;
        $this->ownerId = (string) $link->owner_id;

        // A NULL share comes back as an empty box, not as "0": the two mean
        // different things and a zero default would invent a share nobody set.
        $this->share = $link->ownership_share === null ? '' : (string) $link->ownership_share;

        $this->openedAt = $this->conflictBaseline($link);
    }

    public function store(): DeedOwner
    {
        $this->validate();

        return $this->writeSafely(
            'deed_owner.create',
            'deed_owner',
            null,
            function (): DeedOwner {
                $this->guardAgainstOverAllocation();

                return DeedOwner::create([
                    'deed_id' => $this->deedId(),
                    'owner_id' => (int) $this->ownerId,
                    'ownership_share' => $this->shareValue(),
                    // source_gdb_id stays NULL: a link made by hand has no
                    // ArcGIS feature behind it, and inventing one would
                    // corrupt the provenance the importer matches on.
                ]);
            }
        );
    }

    public function update(): DeedOwner
    {
        $this->validate();

        $link = $this->linkUnderEdit();

        $this->guardAgainstConflict($link, $this->openedAt);

        return $this->writeSafely(
            'deed_owner.update',
            'deed_owner',
            $link->id,
            function () use ($link): DeedOwner {
                $this->guardAgainstOverAllocation();

                // Only the two editable columns: source_gdb_id is provenance
                // from the import and must survive a share correction.
                $link->update([
                    'owner_id' => (int) $this->ownerId,
                    'ownership_share' => $this->shareValue(),
                ]);

                return $link->refresh();
            }
        );
    }

    /**
     * Refuse a share that would push the deed's total past 100%.
     *
     * Called from inside writeSafely()'s transaction, so the total it reads
     * cannot move between the check and the write it is guarding.
     *
     * @throws ValidationException when the projected total overruns the ceiling
     */
    private function guardAgainstOverAllocation(): void
    {
        $deedId = $this->deedId();

        // Locking the deed row serialises every ownership write on that deed.
        // Locking only the sibling rows would not: two concurrent inserts hold
        // no row in common, so each would still see room for the other's share.
        Deed::query()->whereKey($deedId)->lockForUpdate()->firstOrFail();

        $query = DeedOwner::query()->where('deed_id', $deedId);

        if ($this->deedOwnerId !== null) {
            $query->whereKeyNot($this->deedOwnerId);
        }

        $allocated = 0.0;

        foreach ($query->get(['id', 'ownership_share']) as $row) {
            // NULL is "share written as prose", not a known zero. It adds
            // nothing to the numeric total rather than blocking the remainder.
            $allocated += (float) ($row->ownership_share ?? 0);
        }

        $allocated = round($allocated, 2);

        if (round($allocated + ($this->shareValue() ?? 0.0), 2) <= self::CEILING) {
            return;
        }

        // Thrown, not added to the error bag: the exception is what rolls the
        // transaction back. The key carries this form's property name because
        // a manual throw skips the prefixing Form::validate() would have done.
        throw ValidationException::withMessages([
            (string) $this->getPropertyName().'.share' => __('owners.ownership_exceeds', [
                'allocated' => number_format($allocated, 2),
                'remaining' => number_format(max(0.0, self::CEILING - $allocated), 2),
            ]),
        ]);
    }

    /**
     * The row being edited, scoped to the deed the component locked — the row
     * id travels in the request payload and must not reach another deed's row.
     */
    private function linkUnderEdit(): DeedOwner
    {
        return DeedOwner::query()
            ->where('deed_id', $this->deedId())
            ->findOrFail((int) $this->deedOwnerId);
    }

    /** The share as it goes into the column: NULL when the box was left empty. */
    private function shareValue(): ?float
    {
        $raw = trim($this->share);

        return $raw === '' ? null : (float) $raw;
    }

    private function deedId(): int
    {
        if ($this->deedId === null) {
            throw new RuntimeException('OwnershipForm is missing its deed: the component did not set deedId.');
        }

        return $this->deedId;
    }
}
