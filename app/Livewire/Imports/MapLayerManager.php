<?php

declare(strict_types=1);

namespace App\Livewire\Imports;

use App\Models\MapLayer;
use App\Support\Concerns\WritesSafely;
use App\Support\Geo\LayerNames;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The custom map layers — projects and buildings among them: rename,
 * recolour, choose which show when the map opens, download, or delete. A new name close to another layer's is warned about,
 * as on import, but not refused — two similar layers can be meant.
 */
class MapLayerManager extends Component
{
    use WritesSafely;

    #[Locked]
    public ?int $editing = null;

    public string $name = '';

    public string $color = '#8e44ad';

    public bool $visible = false;

    public function mount(): void
    {
        $this->authorizeManage();
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $layer = MapLayer::findOrFail($id);

        $this->editing = $layer->id;
        $this->name = $layer->name;
        $this->color = $layer->color;
        $this->visible = $layer->visible_by_default;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorizeManage();
        abort_if($this->editing === null, 404);

        $this->validate([
            'name' => ['required', 'string', 'max:150', 'unique:map_layers,name,'.$this->editing],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $this->writeSafely('map_layer.update', 'map_layer', $this->editing, function (): void {
            MapLayer::findOrFail($this->editing)->update([
                'name' => trim($this->name),
                'color' => strtolower($this->color),
                'visible_by_default' => $this->visible,
            ]);
        });

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('common.saved'));
    }

    /** Shown or hidden when the map opens, in one click from the list. */
    public function toggleVisible(int $id): void
    {
        $this->authorizeManage();

        $this->writeSafely('map_layer.update', 'map_layer', $id, function () use ($id): void {
            $layer = MapLayer::findOrFail($id);
            $layer->update(['visible_by_default' => ! $layer->visible_by_default]);
        });
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetErrorBag();
    }

    public function delete(int $id): void
    {
        $this->authorizeManage();

        $this->writeSafely('map_layer.delete', 'map_layer', $id, function () use ($id): void {
            // Features go with it (cascade).
            MapLayer::findOrFail($id)->delete();
        });

        $this->dispatch('toast', type: 'success', message: __('common.deleted'));
    }

    public function render(): View
    {
        $layers = MapLayer::query()->orderBy('name')->get();

        // While renaming: the other layers (and the parcels) the new name
        // comes close to.
        $similar = [];
        if ($this->editing !== null && trim($this->name) !== '') {
            $candidates = $layers->where('id', '!=', $this->editing)
                ->map(fn (MapLayer $l): array => ['name' => $l->name])->values()->all();
            foreach (LayerNames::BUILT_IN['parcels'] as $builtIn) {
                $candidates[] = ['name' => $builtIn];
            }
            $similar = array_values(array_unique(array_column(LayerNames::similarTo($this->name, $candidates), 'name')));
        }

        return view('livewire.imports.map-layer-manager', [
            'layers' => $layers,
            'similar' => $similar,
            'uploaders' => DB::table('users')->whereIn('id', $layers->pluck('created_by')->filter())->pluck('name', 'id'),
        ]);
    }

    private function authorizeManage(): void
    {
        abort_unless(Auth::user()?->can('imports.create'), 403);
    }
}
