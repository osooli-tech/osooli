<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Enums\PhotoType;
use App\Livewire\Concerns\FiltersByCreatedAt;
use App\Models\Owner;
use App\Models\ParcelPhoto;
use App\Support\OwnerVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/** The owner's own documents: every query starts from the parcels they hold. */
class DocumentIndex extends Component
{
    use FiltersByCreatedAt;
    use WithPagination;

    public string $search = '';

    public string $filterPhotoType = '';

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
        $this->reset(['search', 'filterPhotoType', 'createdFrom', 'createdTo']);
        $this->resetPage();
    }

    /** @return Builder<ParcelPhoto> */
    private function ownedDocuments(): Builder
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        return OwnerVisibility::documents(ParcelPhoto::query(), $owner);
    }

    /** @return LengthAwarePaginator<ParcelPhoto> */
    private function photos(): LengthAwarePaginator
    {
        return $this->ownedDocuments()
            ->with(['parcel.plan'])
            ->when($this->search !== '', function (Builder $q): void {
                $q->whereHas('parcel', fn ($p) => $p->whereLike('parcel_no', '%'.$this->search.'%'));
            })
            ->when($this->filterPhotoType !== '', fn (Builder $q) => $q->where('photo_type', $this->filterPhotoType))
            ->latest()
            ->tap(fn (Builder $q) => $this->applyCreatedAt($q))
            ->paginate(25);
    }

    public function render(): View
    {
        $counts = $this->ownedDocuments()->selectRaw('photo_type, count(*) as total')->groupBy('photo_type')->pluck('total', 'photo_type');

        return view('livewire.portal.document-index', [
            'photos' => $this->photos(),
            'counts' => $counts,
            'totalDocuments' => (int) $counts->sum(),
            'photoTypeOptions' => collect(PhotoType::cases())->mapWithKeys(
                fn (PhotoType $type) => [$type->value => __('documents.photo_types.'.$type->value)]
            )->all(),
        ]);
    }
}
