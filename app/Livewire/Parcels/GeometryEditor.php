<?php

declare(strict_types=1);

namespace App\Livewire\Parcels;

use App\Models\Parcel;
use App\Models\User;
use App\Support\Concerns\WritesSafely;
use App\Support\OwnerScope;
use App\Support\ParcelGeometry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Redraw a parcel's polygon on the map, or draw its first one.
 *
 * The browser only proposes a shape; ParcelGeometry decides whether it is
 * acceptable and writes it. Every replaced polygon is kept as a revision, so a
 * slip of the mouse on surveyed data can always be undone from this dialog.
 */
class GeometryEditor extends Component
{
    use WritesSafely;

    public bool $show = false;

    #[Locked]
    public ?int $parcelId = null;

    /** `updated_at` as it stood when the editor opened. */
    #[Locked]
    public ?string $openedAt = null;

    /**
     * Parcels the last submitted shape overlaps. Non-empty means the save is
     * waiting for the user to confirm it anyway.
     *
     * @var list<string>
     */
    public array $overlaps = [];

    #[On('parcel-geometry-edit')]
    public function open(int $parcelId): void
    {
        $this->authorizeFor($parcelId);

        $parcel = Parcel::findOrFail($parcelId);

        $this->parcelId = $parcel->id;
        $this->openedAt = $this->conflictBaseline($parcel);
        $this->overlaps = [];
        $this->resetErrorBag();
        $this->show = true;
    }

    public function save(string $geojson, bool $confirmOverlap = false): void
    {
        $parcelId = $this->editingParcel();
        $this->resetErrorBag();

        try {
            $checked = ParcelGeometry::validate($geojson);
        } catch (InvalidArgumentException $exception) {
            $this->addError('geometry', $exception->getMessage());

            return;
        }

        if (! $confirmOverlap) {
            $this->overlaps = ParcelGeometry::overlaps($parcelId, $checked['geojson']);

            if ($this->overlaps !== []) {
                return;
            }
        }

        if ($this->write($parcelId, $checked['geojson'], 'edit')) {
            $this->finish(__('parcels.geometry_saved'));
        }
    }

    /** Bring back the polygon a revision holds; the current one becomes a revision itself. */
    public function restore(int $revisionId): void
    {
        $parcelId = $this->editingParcel();
        $this->resetErrorBag();

        try {
            $geojson = ParcelGeometry::revisionGeometry($parcelId, $revisionId);
        } catch (InvalidArgumentException $exception) {
            $this->addError('geometry', $exception->getMessage());

            return;
        }

        if ($this->write($parcelId, $geojson, 'restore')) {
            $this->finish(__('parcels.geometry_restored'));
        }
    }

    public function close(): void
    {
        $this->show = false;
        $this->overlaps = [];
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $parcelId = $this->show ? $this->parcelId : null;
        $parcel = $parcelId !== null ? Parcel::with(['deeds', 'boundary'])->find($parcelId) : null;

        return view('livewire.parcels.geometry-editor', [
            'parcel' => $parcel,
            'config' => $parcel === null ? null : [
                'token' => (string) config('services.mapbox.token'),
                'geometry' => ParcelGeometry::current($parcel->id),
                'neighbours' => ParcelGeometry::neighbours($parcel->id),
                'bounds' => ParcelGeometry::fallbackBounds($parcel->id),
                'i18n' => [
                    'empty' => __('parcels.geometry_errors.empty'),
                    'paste_invalid' => __('parcels.geometry_paste_invalid'),
                    'map_failed' => __('parcels.geometry_map_failed'),
                ],
            ],
            'revisions' => $parcel === null ? [] : ParcelGeometry::revisions($parcel->id),
            'deedArea' => $parcel?->deeds->max('deed_area'),
            'measuredArea' => $parcel?->boundary?->measured_area,
        ]);
    }

    /**
     * One write, one transaction: lock the parcel, check nobody saved it since
     * the editor opened, keep the old polygon, store the new one, audit it.
     */
    private function write(int $parcelId, ?string $geojson, string $action): bool
    {
        try {
            $this->writeSafely('parcel.geometry_'.$action, 'parcel', $parcelId, function () use ($parcelId, $geojson, $action): void {
                $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcelId);

                // A parcel imported without timestamps has no baseline to
                // compare against; there is then nothing to guard with.
                if ($this->openedAt !== null) {
                    $this->guardAgainstConflict($parcel, $this->openedAt);
                }

                ParcelGeometry::replace($parcelId, $geojson, $action);
            });
        } catch (RuntimeException $exception) {
            // Only the optimistic-lock refusal is meant for the user; a
            // QueryException is a RuntimeException too and must not be shown.
            if ($exception->getMessage() !== __('common.conflict')) {
                throw $exception;
            }

            $this->addError('geometry', $exception->getMessage());

            return false;
        }

        return true;
    }

    private function finish(string $message): void
    {
        $this->show = false;
        $this->overlaps = [];
        $this->dispatch('parcel-geometry-saved', parcelId: $this->parcelId);
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /**
     * The parcel this editor is open on, re-checked on every action: the id is
     * locked, but the permission and the owner scope may have changed since.
     */
    private function editingParcel(): int
    {
        abort_if($this->parcelId === null, 404);

        $this->authorizeFor($this->parcelId);

        return $this->parcelId;
    }

    private function authorizeFor(int $parcelId): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $user->can('parcels.edit_geometry'), 403);
        abort_unless(OwnerScope::canSeeParcel($user, $parcelId), 403);

        // Archived parcels resolve to 404 through the SoftDeletes scope.
        abort_unless(DB::table('parcels')->where('id', $parcelId)->whereNull('deleted_at')->exists(), 404);
    }
}
