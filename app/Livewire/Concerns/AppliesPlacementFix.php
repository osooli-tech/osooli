<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\District;
use App\Models\Plan;
use App\Support\Concerns\WritesSafely;
use App\Support\OwnerScope;
use Illuminate\Support\Facades\Auth;

/**
 * Carrying out a PlacementFix option — moving a plan to another district or
 * a district to another city — the same way from every screen that offers
 * it: permission, audit, and a message naming what moved.
 */
trait AppliesPlacementFix
{
    use WritesSafely;

    /**
     * A move takes a whole plan or district along, and so parcels beyond any
     * one owner's: never for a user limited to some owners' parcels.
     */
    protected function mayFixPlacement(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->can('parcels.placement_fix') && OwnerScope::parcelIds($user) === null;
    }

    protected function authorizePlacementFix(): void
    {
        abort_unless($this->mayFixPlacement(), 403);
    }

    /**
     * @param  array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}|null  $option  null when it no longer stands
     */
    protected function applyPlacementOption(?array $option): bool
    {
        if ($option === null) {
            $this->dispatch('toast', type: 'error', message: __('placement.fix.stale'));

            return false;
        }

        $target = $option['target_id'];

        if ($option['kind'] === 'plan') {
            $plan = Plan::query()->findOrFail($option['subject_id']);
            $this->writeSafely('plan.reassign', 'plan', (int) $plan->id, fn (): bool => $plan->update(['district_id' => $target]));
        } else {
            $district = District::query()->findOrFail($option['subject_id']);
            $this->writeSafely('district.reassign', 'district', (int) $district->id, fn (): bool => $district->update(['city_id' => $target]));
        }

        $this->dispatch('toast', type: 'success', message: __('placement.fix.done_'.$option['kind'], [
            'subject' => $option['subject'],
            'to' => $option['to'],
            'parcels' => $option['parcels'],
        ]));

        return true;
    }
}
