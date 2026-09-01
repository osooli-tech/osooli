<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\PhotoType;

/**
 * How a PDF filename identifies the parcel it belongs to.
 *
 * Neither side of the survey-map rule may assume digits — plan "20A" exists.
 */
enum DocumentRule: string
{
    case Deed = 'deed';
    case SurveyMap = 'survey_map';

    public function matches(string $stem): bool
    {
        return match ($this) {
            self::Deed => preg_match('/^\d{10,14}$/', $stem) === 1,
            self::SurveyMap => preg_match('/^(.+?)\s*-\s*(.+?)$/u', $stem) === 1,
        };
    }

    public function photoType(): PhotoType
    {
        return match ($this) {
            self::Deed => PhotoType::Deed,
            self::SurveyMap => PhotoType::BoundarySurvey,
        };
    }

    public function subdirectory(): string
    {
        return match ($this) {
            self::Deed => 'documents/deeds',
            self::SurveyMap => 'documents/surveys',
        };
    }
}
