<?php

declare(strict_types=1);

namespace App\Livewire\SurveyDecisions;

use App\Enums\QrarSource;
use App\Models\SurveyDecision;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class SurveyDecisionIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterQrarSource = '';

    /** @var array<string, string> */
    public array $qrarSourceOptions = [];

    public function mount(): void
    {
        $this->qrarSourceOptions = array_column(
            array_map(
                fn (QrarSource $e) => ['value' => $e->value, 'label' => __('survey_decisions.qrar_sources.'.$e->value)],
                QrarSource::cases()
            ),
            'label',
            'value'
        );
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterQrarSource(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterQrarSource']);
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<SurveyDecision> */
    public function decisions(): LengthAwarePaginator
    {
        return SurveyDecision::query()
            ->with(['parcel.plan'])
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('folder', 'ilike', $term)
                        ->orWhere('qrar_no', 'ilike', $term)
                        ->orWhereHas('parcel', fn ($p) => $p->where('parcel_no', 'ilike', $term));
                });
            })
            ->when($this->filterQrarSource !== '', fn ($q) => $q->where('qrar_source', $this->filterQrarSource))
            ->orderBy('id')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.survey-decisions.survey-decision-index', [
            'decisions' => $this->decisions(),
        ]);
    }
}
