<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Models\Parcel;
use App\Models\SurveyDecision;
use App\Support\Concerns\WritesSafely;
use App\Support\DatabaseEnum;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * One survey decision (قرار مساحي) belonging to a parcel.
 *
 * Unlike the boundary, a parcel may hold many of these — the column carries no
 * unique index — so this form creates and updates rows the ordinary way rather
 * than upserting on parcel_id.
 */
class SurveyDecisionForm extends Form
{
    use WritesSafely;

    public ?int $decisionId = null;

    public ?int $parcelId = null;

    public string $qrarNo = '';

    public string $reportNo = '';

    public string $folder = '';

    public string $qrarSource = '';

    /** `updated_at` as it stood when the record was opened. */
    public ?string $openedAt = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'parcelId' => ['required', 'integer', Rule::exists('parcels', 'id')],
            'qrarNo' => ['nullable', 'string', 'max:100'],
            'reportNo' => ['nullable', 'string', 'max:100'],
            'folder' => ['nullable', 'string', 'max:255'],

            // Read from pg_enum rather than hand-listed, so a label added to
            // qrar_source_enum by a migration is accepted without a second edit
            // here — and one the column would reject never passes.
            'qrarSource' => ['nullable', DatabaseEnum::rule('qrar_source')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'parcelId' => __('survey_decisions.parcel_no'),
            'qrarNo' => __('survey_decisions.qrar_no'),
            'reportNo' => __('survey_decisions.report_no'),
            'folder' => __('survey_decisions.folder'),
            'qrarSource' => __('survey_decisions.qrar_source'),
        ];
    }

    /** Start a new decision on the given parcel. */
    public function setParcel(Parcel $parcel): void
    {
        $this->decisionId = null;
        $this->parcelId = $parcel->id;
        $this->qrarNo = '';
        $this->reportNo = '';
        $this->folder = '';
        $this->qrarSource = '';
        $this->openedAt = null;
    }

    /** Load an existing decision for editing, capturing the conflict baseline. */
    public function setDecision(SurveyDecision $decision): void
    {
        $this->decisionId = $decision->id;
        $this->parcelId = $decision->parcel_id;
        $this->qrarNo = (string) $decision->qrar_no;
        $this->reportNo = (string) $decision->report_no;
        $this->folder = (string) $decision->folder;
        // The stored label rather than the cast enum. The column is nullable —
        // plenty of imported rows carry no source — and the label is exactly
        // what <x-form.enum-select> offers, since both come from pg_enum.
        $this->qrarSource = (string) $decision->getRawOriginal('qrar_source');
        $this->openedAt = $this->conflictBaseline($decision);
    }

    public function store(): SurveyDecision
    {
        $this->validate();

        return $this->writeSafely(
            'survey_decision.create',
            'survey_decision',
            null,
            fn (): SurveyDecision => SurveyDecision::create($this->attributes())
        );
    }

    public function update(): SurveyDecision
    {
        $this->validate();

        $decision = SurveyDecision::findOrFail($this->decisionId);

        $this->guardAgainstConflict($decision, $this->openedAt);

        return $this->writeSafely(
            'survey_decision.update',
            'survey_decision',
            $decision->id,
            function () use ($decision): SurveyDecision {
                // Through the model, not a raw update: `qrar_source` is cast to
                // the QrarSource enum, and that cast is what turns the posted
                // label into a value the Postgres enum column accepts.
                $decision->update($this->attributes());

                return $decision->refresh();
            }
        );
    }

    /**
     * Form values mapped onto column names, with blanks stored as NULL so an
     * emptied field reads as "not recorded" instead of an empty string.
     *
     * @return array<string, string|int|null>
     */
    private function attributes(): array
    {
        return [
            'parcel_id' => $this->parcelId,
            'qrar_no' => $this->qrarNo !== '' ? $this->qrarNo : null,
            'report_no' => $this->reportNo !== '' ? $this->reportNo : null,
            'folder' => $this->folder !== '' ? $this->folder : null,
            'qrar_source' => $this->qrarSource !== '' ? $this->qrarSource : null,
        ];
    }
}
