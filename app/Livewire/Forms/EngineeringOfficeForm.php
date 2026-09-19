<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\EngineeringOffice;
use App\Support\Concerns\WritesSafely;
use Livewire\Form;

/**
 * The surveying office credited on a parcel boundary.
 *
 * Offices stand on their own — no parent in the location chain — and this form
 * is what lets one be added by name instead of being referenced by its numeric
 * id from the boundary screen.
 */
class EngineeringOfficeForm extends Form
{
    use WritesSafely;

    public ?int $officeId = null;

    public string $name = '';

    public string $licenseNo = '';

    public string $phone = '';

    public string $email = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'licenseNo' => ['nullable', 'string', 'max:100'],

            // Same Saudi mobile shape OwnerForm accepts, but the column here is
            // an office line, so a landline prefix has to pass as well.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^(?:\+?966|0)?[1-9]\d{7,9}$/'],

            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('reference.office_name'),
            'licenseNo' => __('reference.license_no'),
            'phone' => __('reference.phone'),
            'email' => __('reference.email'),
        ];
    }

    /** Load an existing office for editing, capturing the conflict baseline. */
    public function setOffice(EngineeringOffice $office): void
    {
        $this->officeId = $office->id;
        $this->name = (string) $office->name;
        $this->licenseNo = (string) $office->license_no;
        $this->phone = (string) $office->phone;
        $this->email = (string) $office->email;
        $this->openedAt = $this->conflictBaseline($office);
    }

    public function store(): EngineeringOffice
    {
        $this->validate();

        return $this->writeSafely(
            'engineering_office.create',
            'engineering_office',
            null,
            fn (): EngineeringOffice => EngineeringOffice::create($this->attributes())
        );
    }

    public function update(): EngineeringOffice
    {
        $this->validate();

        $office = EngineeringOffice::findOrFail($this->officeId);

        $this->guardAgainstConflict($office, $this->openedAt);

        return $this->writeSafely(
            'engineering_office.update',
            'engineering_office',
            $office->id,
            function () use ($office): EngineeringOffice {
                $office->update($this->attributes());

                return $office->refresh();
            }
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function attributes(): array
    {
        return [
            'name' => $this->name,
            'license_no' => $this->licenseNo !== '' ? $this->licenseNo : null,
            'phone' => $this->phone !== '' ? $this->phone : null,
            'email' => $this->email !== '' ? $this->email : null,
        ];
    }
}
