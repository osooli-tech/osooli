<?php

declare(strict_types=1);

namespace App\Livewire\SurveyDecisions;

use App\Livewire\Forms\ParcelBoundaryForm;
use App\Livewire\Forms\SurveyDecisionForm;
use App\Models\EngineeringOffice;
use App\Models\Parcel;
use App\Models\SurveyDecision;
use App\Support\Concerns\WritesSafely;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use RuntimeException;

/**
 * Edits a survey decision and the parcel's surveyed boundary in one dialog.
 *
 * The two live together because that is how the data is captured in the field:
 * the decision paper and the sketch it refers to arrive as one document. They
 * are still two tables with two permissions, so the boundary half only appears
 * — and is only written — for a user who may edit parcels.
 */
class SurveyDecisionFormModal extends Component
{
    use WritesSafely;

    public SurveyDecisionForm $decision;

    public ParcelBoundaryForm $boundary;

    public bool $show = false;

    #[Locked]
    public ?int $parcelId = null;

    #[Locked]
    public ?int $decisionId = null;

    /**
     * The dialog opened for the boundary alone, from the parcel's boundary
     * card. The decision half is then neither shown nor written: saving it
     * would insert a blank decision row every time a boundary was corrected.
     */
    #[Locked]
    public bool $boundaryOnly = false;

    #[On('open-parcel-boundary')]
    public function openBoundary(int $parcelId): void
    {
        abort_unless($this->canEditBoundary(), 403);

        $parcel = Parcel::query()->with('boundary')->findOrFail($parcelId);

        $this->parcelId = $parcel->id;
        $this->decisionId = null;
        $this->boundaryOnly = true;
        $this->resetValidation();
        $this->boundary->setParcel($parcel);

        $this->show = true;
    }

    #[On('open-survey-decision')]
    public function open(int $parcelId, ?int $decisionId = null): void
    {
        // The call site hides the trigger behind @can, but that only removes the
        // markup — a request aimed straight at this component still arrives, so
        // the check has to live here too.
        abort_unless(auth()->user()?->can('survey_decisions.edit'), 403);

        $parcel = Parcel::query()->with('boundary')->findOrFail($parcelId);

        $this->parcelId = $parcel->id;
        $this->decisionId = $decisionId;
        $this->boundaryOnly = false;
        $this->resetValidation();

        if ($decisionId === null) {
            $this->decision->setParcel($parcel);
        } else {
            // Scoped to the parcel, not a bare findOrFail: a forged id must not
            // be able to reach another parcel's decision through this dialog.
            $this->decision->setDecision(
                SurveyDecision::query()->where('parcel_id', $parcel->id)->findOrFail($decisionId)
            );
        }

        $this->boundary->setParcel($parcel);

        $this->show = true;
    }

    public function save(): void
    {
        abort_unless(
            $this->boundaryOnly ? $this->canEditBoundary() : auth()->user()?->can('survey_decisions.edit'),
            403
        );

        // Which parcel and which decision are being written is decided here and
        // re-pinned from the locked copies: the forms' own ids travel to the
        // client with the rest of the form state, so a tampered payload could
        // otherwise aim the write at a different parcel's record.
        $this->decision->parcelId = $this->parcelId;
        $this->decision->decisionId = $this->decisionId;
        $this->boundary->parcelId = $this->parcelId;

        $writesDecision = ! $this->boundaryOnly;

        // The boundary is saved with updateOrCreate(), so an untouched, empty
        // boundary section would create a blank boundary row every time a
        // decision is added to a parcel that has none yet.
        $writesBoundary = $this->canEditBoundary() && ! $this->boundaryIsNewAndBlank();

        if ($this->boundaryOnly && ! $writesBoundary) {
            $this->addError('boundary.nBorder', __('survey_decisions.boundary_empty'));

            return;
        }

        // A new decision with every field blank is not a decision — it is the
        // side effect of a dialog opened to fix the boundary, and it would sit
        // in the survey list as an empty row nobody can explain.
        if ($writesDecision && $this->decisionId === null && $this->decisionIsBlank()) {
            $this->addError('decision.qrarNo', __('survey_decisions.decision_empty'));

            return;
        }

        try {
            // Validate everything before writing anything: otherwise a decision
            // could be committed and the boundary then rejected, leaving the
            // user looking at a field error over a change that half-landed.
            if ($writesDecision) {
                $this->decision->validate();
            }

            if ($writesBoundary) {
                $this->boundary->validate();
            }

            // One user action, one transaction. writeSafely() opens its own
            // transaction per write; nested, those become savepoints, so the
            // decision and the boundary either both land or neither does.
            DB::transaction(function () use ($writesBoundary, $writesDecision): void {
                if ($writesDecision) {
                    $this->decision->decisionId === null
                        ? $this->decision->store()
                        : $this->decision->update();
                }

                if ($writesBoundary) {
                    $this->boundary->save();
                }
            });
        } catch (RuntimeException $exception) {
            // guardAgainstConflict() signals a lost optimistic lock by throwing a
            // plain RuntimeException carrying an already-translated message, and
            // that is a normal outcome the user can act on. Subclasses mean
            // something else — a database failure, a record deleted underneath
            // us — and belong to the error handler rather than a toast.
            if ($exception::class !== RuntimeException::class) {
                throw $exception;
            }

            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->show = false;
        $this->dispatch('survey-decision-saved');
        $this->dispatch('toast', type: 'success', message: __(
            $this->boundaryOnly ? 'survey_decisions.boundary_saved' : 'survey_decisions.saved'
        ));
    }

    /**
     * Delete a survey decision outright.
     *
     * Decisions are not archived: they are supporting paperwork rather than a
     * core record, nothing references them, and the audit entry keeps who
     * removed which one. The parcel scope stops a forged id reaching a
     * decision the caller was not looking at.
     */
    #[On('survey-decision-delete')]
    public function delete(int $parcelId, int $decisionId): void
    {
        abort_unless(auth()->user()?->can('survey_decisions.edit'), 403);

        $decision = SurveyDecision::query()->where('parcel_id', $parcelId)->findOrFail($decisionId);

        $this->writeSafely(
            'survey_decision.delete',
            'survey_decision',
            $decision->id,
            fn (): bool => (bool) $decision->delete()
        );

        $this->dispatch('survey-decision-deleted');
        $this->dispatch('toast', type: 'success', message: __('survey_decisions.deleted'));
    }

    public function close(): void
    {
        $this->show = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.survey-decisions.survey-decision-form-modal', [
            'engineeringOffices' => $this->engineeringOfficeOptions(),
            'canEditBoundary' => $this->canEditBoundary(),
        ]);
    }

    private function boundaryIsNewAndBlank(): bool
    {
        if ($this->boundary->boundaryId !== null) {
            return false;
        }

        foreach (['nBorder', 'sBorder', 'eBorder', 'wBorder', 'nDim', 'sDim', 'eDim', 'wDim',
            'measuredArea', 'matchesDeed', 'surveyDate', 'engineeringOfficeId'] as $field) {
            if (trim($this->boundary->{$field}) !== '') {
                return false;
            }
        }

        return true;
    }

    private function decisionIsBlank(): bool
    {
        return trim($this->decision->qrarNo) === ''
            && trim($this->decision->reportNo) === ''
            && trim($this->decision->folder) === ''
            && $this->decision->qrarSource === '';
    }

    private function canEditBoundary(): bool
    {
        return auth()->user()?->can('parcels.edit') === true;
    }

    /**
     * Engineering offices as an option map for <x-form.select>, name shown and
     * id stored, so nobody has to know or type a numeric id.
     *
     * @return array<int, string>
     */
    private function engineeringOfficeOptions(): array
    {
        return EngineeringOffice::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
