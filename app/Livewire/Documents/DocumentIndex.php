<?php

declare(strict_types=1);

namespace App\Livewire\Documents;

use App\Enums\PhotoType;
use App\Models\ParcelPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterPhotoType = '';

    /** @var array<string, string> */
    public array $photoTypeOptions = [];

    public function mount(): void
    {
        $this->photoTypeOptions = array_column(
            array_map(
                fn (PhotoType $e) => ['value' => $e->value, 'label' => __('documents.photo_types.'.$e->value)],
                PhotoType::cases()
            ),
            'label',
            'value'
        );
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPhotoType(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'filterPhotoType']);
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<ParcelPhoto> */
    public function photos(): LengthAwarePaginator
    {
        return ParcelPhoto::query()
            ->with(['parcel.plan'])
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->whereHas('parcel', fn ($p) => $p->where('parcel_no', 'ilike', $term));
            })
            ->when($this->filterPhotoType !== '', fn ($q) => $q->where('photo_type', $this->filterPhotoType))
            ->latest()
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.documents.document-index', [
            'photos' => $this->photos(),
        ]);
    }
}
