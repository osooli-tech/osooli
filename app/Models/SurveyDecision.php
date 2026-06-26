<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QrarSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $parcel_id
 * @property string|null $folder
 * @property string|null $report_no
 * @property string|null $qrar_no
 * @property QrarSource|null $qrar_source
 */
class SurveyDecision extends Model
{
    protected $table = 'survey_decisions';

    protected $fillable = [
        'parcel_id',
        'qrar_source',
        'qrar_no',
        'report_no',
        'folder',
    ];

    protected function casts(): array
    {
        return [
            'qrar_source' => QrarSource::class,
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }
}
