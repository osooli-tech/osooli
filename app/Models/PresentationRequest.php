<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A visitor's request for a demo, raised from the public landing page.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $message
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PresentationRequest extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
