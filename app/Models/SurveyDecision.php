<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }
}
