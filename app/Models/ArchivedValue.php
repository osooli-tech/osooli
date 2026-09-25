<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One field value removed or corrected by a cleanup, kept so it can be put
 * back. The record it came from stays in use; only this value left it.
 *
 * @property int $id
 * @property string $record_table
 * @property int $record_id
 * @property string $field
 * @property string|null $value
 * @property string $reason
 * @property int|null $archived_by
 */
class ArchivedValue extends Model
{
    // Written once; the only later change is deleting it on restore.
    public $timestamps = false;

    /**
     * The fields a cleanup may archive, and so the only ones a restore may
     * write back. The row's table and field come from the database, but a
     * restore is still a write driven by stored strings, so it is bounded.
     */
    public const FIELDS = [
        'owners' => ['name', 'national_id', 'phone'],
        'deeds' => ['deed_no', 'deed_date_hijri'],
        'parcel_boundaries' => ['n_border', 's_border', 'e_border', 'w_border'],
        'parcels' => ['plan_id'],
        'plans' => ['district_id'],
        'districts' => ['city_id', 'name_en'],
    ];

    protected $fillable = ['record_table', 'record_id', 'field', 'value', 'reason', 'archived_by'];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Keep `$value` as the current value of one field, then write `$new` in
     * its place. Does nothing when the field no longer holds `$value`, so a
     * cleanup run twice, or run after someone fixed the field by hand, leaves
     * it alone.
     */
    public static function replace(string $table, int $id, string $field, ?string $value, ?string $new, string $reason): bool
    {
        self::assertArchivable($table, $field);

        $current = DB::table($table)->where('id', $id)->value($field);

        if ($current === null || (string) $current !== $value) {
            return false;
        }

        self::create([
            'record_table' => $table,
            'record_id' => $id,
            'field' => $field,
            'value' => $value,
            'reason' => $reason,
            'archived_by' => Auth::id(),
        ]);

        self::write($table, $id, $field, $new);

        return true;
    }

    /** Put the value back on its record and drop it from the archive. */
    public function restore(): void
    {
        self::assertArchivable($this->record_table, $this->field);

        self::write($this->record_table, $this->record_id, $this->field, $this->value);

        $this->delete();
    }

    /** @return BelongsTo<User, $this> */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    private static function write(string $table, int $id, string $field, ?string $value): void
    {
        // Through the model, so phone_normalized follows the phone.
        if ($table === 'owners') {
            $owner = Owner::withTrashed()->whereKey($id)->first();
            $owner?->setAttribute($field, $value)->save();

            return;
        }

        DB::table($table)->where('id', $id)->update([$field => $value, 'updated_at' => now()]);
    }

    private static function assertArchivable(string $table, string $field): void
    {
        if (! in_array($field, self::FIELDS[$table] ?? [], true)) {
            throw new InvalidArgumentException("{$table}.{$field} is not an archivable field.");
        }
    }
}
