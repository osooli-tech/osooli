<?php

declare(strict_types=1);

namespace App\Livewire\Reference;

use App\Livewire\Concerns\AppliesPlacementFix;
use App\Support\Geo\PlacementFix;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * «الحي حسب الموقع» on a plan's row: where the plan's parcels actually lie,
 * by the district and city boundaries, and the move that puts the plan in
 * the district that holds them — or, for a district with no boundary of its
 * own, the district in the city that does.
 */
class PlanPlacement extends Component
{
    use AppliesPlacementFix;

    public bool $show = false;

    public ?int $planId = null;

    /** @var array{plan: string, current: string, parcels: int, located: int, spread: list<array{name: string, parcels: int}>, options: list<array{kind: string, subject_id: int, subject: string, target_id: int, from: string, to: string, parcels: int, located: int, inside: int}>}|null */
    public ?array $report = null;

    #[On('plan-placement')]
    public function open(int $id): void
    {
        $this->authorizePlacementFix();

        $this->planId = $id;
        $this->report = PlacementFix::forPlan($id);
        $this->show = $this->report !== null;
    }

    public function close(): void
    {
        $this->reset(['show', 'planId', 'report']);
    }

    public function applyFix(string $kind, int $targetId): void
    {
        $this->authorizePlacementFix();

        $applied = $this->applyPlacementOption($this->planId === null ? null : PlacementFix::findForPlan($this->planId, $kind, $targetId));

        $this->close();
        if ($applied) {
            $this->dispatch('plan-placed');
        }
    }

    public function render(): View
    {
        return view('livewire.reference.plan-placement');
    }
}
