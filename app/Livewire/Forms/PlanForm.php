<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Plan;
use App\Support\Concerns\WritesSafely;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * A subdivision plan (مخطط), the record a parcel hangs off.
 *
 * This is the form that unblocks parcel entry: until it existed a parcel whose
 * plan was not already in the database could not be entered at all.
 */
class PlanForm extends Form
{
    use WritesSafely;

    public ?int $planId = null;

    public string $planNo = '';

    /**
     * Held as a string because a <select> posts strings and its placeholder
     * option posts ''. The column is nullable, so '' means "no district yet".
     */
    public string $districtId = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // plan_no carries a UNIQUE index; ignoring self keeps an edit that
            // leaves the number alone from failing against its own row.
            'planNo' => [
                'required', 'string', 'max:50',
                Rule::unique('plans', 'plan_no')->ignore($this->planId),
            ],

            'districtId' => ['nullable', 'integer', 'exists:districts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'planNo' => __('reference.plan_no'),
            'districtId' => __('reference.district'),
        ];
    }

    /** Load an existing plan for editing, capturing the conflict baseline. */
    public function setPlan(Plan $plan): void
    {
        $this->planId = $plan->id;
        $this->planNo = (string) $plan->plan_no;
        $this->districtId = (string) $plan->district_id;
        $this->openedAt = $this->conflictBaseline($plan);
    }

    public function store(): Plan
    {
        $this->validate();

        return $this->writeSafely(
            'plan.create',
            'plan',
            null,
            fn (): Plan => Plan::create($this->attributes())
        );
    }

    public function update(): Plan
    {
        $this->validate();

        $plan = Plan::findOrFail($this->planId);

        $this->guardAgainstConflict($plan, $this->openedAt);

        return $this->writeSafely(
            'plan.update',
            'plan',
            $plan->id,
            function () use ($plan): Plan {
                $plan->update($this->attributes());

                return $plan->refresh();
            }
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    private function attributes(): array
    {
        return [
            'plan_no' => $this->planNo,
            'district_id' => $this->districtId !== '' ? (int) $this->districtId : null,
        ];
    }
}
