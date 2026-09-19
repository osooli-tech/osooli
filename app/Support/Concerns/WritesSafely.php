<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single write path for every edit form in the dashboard.
 *
 * Three guarantees a hand-written save() keeps forgetting:
 *
 *  - Atomic. Editing a parcel touches three tables; either all of it lands or
 *    none of it does.
 *  - Audited. Every write records who made it and which fields moved, so the
 *    audit log stops covering only the handful of actions wired up by hand.
 *  - Conflict-aware. The form carries the row's `updated_at` from the moment it
 *    was opened. If the row moved since, the save is refused instead of
 *    silently overwriting someone else's work.
 */
trait WritesSafely
{
    /**
     * Run a write inside a transaction, recording an audit entry for it.
     *
     * @template TValue
     *
     * @param  callable():TValue  $write
     * @return TValue
     */
    protected function writeSafely(string $action, string $targetType, ?int $targetId, callable $write): mixed
    {
        return DB::transaction(function () use ($action, $targetType, $targetId, $write): mixed {
            $result = $write();

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId ?? ($result instanceof Model ? $result->getKey() : null),
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
            ]);

            return $result;
        });
    }

    /**
     * Refuse the save when the row changed after the form was opened.
     *
     * `$openedAt` is the `updated_at` the form captured on load. A null value
     * means the form never captured one, which is a wiring mistake rather than
     * a reason to skip the check — so it fails loudly.
     *
     * @throws RuntimeException when the record moved underneath the form
     */
    protected function guardAgainstConflict(Model $record, ?string $openedAt): void
    {
        if ($openedAt === null) {
            throw new RuntimeException(
                'Optimistic lock is missing its baseline: the form did not capture updated_at on load.'
            );
        }

        $current = $record->getAttribute('updated_at');

        if ($current === null) {
            return;
        }

        if ($current->toIso8601String() !== $openedAt) {
            throw new RuntimeException(__('common.conflict'));
        }
    }

    /**
     * The baseline a form stores when it opens a record for editing.
     */
    protected function conflictBaseline(Model $record): ?string
    {
        return $record->getAttribute('updated_at')?->toIso8601String();
    }

    /**
     * Fields that actually changed, for the audit trail and for deciding
     * whether a save is a no-op worth skipping.
     *
     * @param  array<string, mixed>  $incoming
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function changedFields(Model $record, array $incoming): array
    {
        $changes = [];

        foreach ($incoming as $field => $value) {
            $before = $record->getAttribute($field);

            if ($before instanceof \BackedEnum) {
                $before = $before->value;
            }

            // Loose comparison on purpose: form input arrives as strings while
            // the model may hold ints or decimals, and "12" equalling 12 here
            // is the correct reading of "the user changed nothing".
            if ($before != $value) {
                $changes[$field] = ['from' => $before, 'to' => $value];
            }
        }

        return $changes;
    }
}
