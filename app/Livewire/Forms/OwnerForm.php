<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Owner;
use App\Support\Concerns\WritesSafely;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * The reference form object every other entity form is modelled on.
 *
 * Fields, rules, Arabic labels and the write itself live together, so the
 * create screen and the edit screen cannot drift apart — they call the same
 * object. A component that uses this is three lines long.
 *
 * Note it carries all five owner columns. The screen it replaces edited only
 * three, leaving `email` and `whatsapp` visible but unreachable.
 */
class OwnerForm extends Form
{
    use WritesSafely;

    public ?int $ownerId = null;

    public string $name = '';

    public string $nationalId = '';

    public string $phone = '';

    public string $email = '';

    public string $whatsapp = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Unique only among rows that have one; the column is nullable and
            // a partial unique index in Postgres enforces the same shape.
            'nationalId' => [
                'nullable', 'string', 'max:50',
                Rule::unique('owners', 'national_id')->ignore($this->ownerId),
            ],

            'phone' => ['nullable', 'string', 'regex:/^(?:\+?966|0)?5\d{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'regex:/^(?:\+?966|0)?5\d{8}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('owners.name'),
            'nationalId' => __('owners.national_id'),
            'phone' => __('owners.phone'),
            'email' => __('owners.email'),
            'whatsapp' => __('owners.whatsapp'),
        ];
    }

    /** Load an existing owner for editing, capturing the conflict baseline. */
    public function setOwner(Owner $owner): void
    {
        $this->ownerId = $owner->id;
        $this->name = $owner->name;
        $this->nationalId = (string) $owner->national_id;
        $this->phone = (string) $owner->phone;
        $this->email = (string) $owner->email;
        $this->whatsapp = (string) $owner->whatsapp;
        $this->openedAt = $this->conflictBaseline($owner);
    }

    public function store(): Owner
    {
        $this->validate();

        return $this->writeSafely(
            'owner.create',
            'owner',
            null,
            fn (): Owner => Owner::create($this->attributes())
        );
    }

    public function update(): Owner
    {
        $this->validate();

        $owner = Owner::findOrFail($this->ownerId);

        $this->guardAgainstConflict($owner, $this->openedAt);

        return $this->writeSafely(
            'owner.update',
            'owner',
            $owner->id,
            function () use ($owner): Owner {
                // Through the model, not a raw update: Owner::booted() keeps
                // `phone_normalized` in step with every phone change, and a
                // direct query would leave the lookup column stale.
                $owner->update($this->attributes());

                return $owner->refresh();
            }
        );
    }

    /**
     * Form values mapped onto column names, with blanks stored as NULL so an
     * emptied field does not become an empty string that breaks uniqueness.
     *
     * @return array<string, string|null>
     */
    private function attributes(): array
    {
        return [
            'name' => $this->name,
            'national_id' => $this->nationalId !== '' ? $this->nationalId : null,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'email' => $this->email !== '' ? $this->email : null,
            'whatsapp' => $this->whatsapp !== '' ? $this->whatsapp : null,
        ];
    }
}
