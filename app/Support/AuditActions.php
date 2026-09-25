<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Lang;

/**
 * How a stored audit action reads on screen.
 *
 * Actions are written as codes — `parcel.update`, `deed.archive`,
 * `create_user` — by some forty call sites. This is the one place that turns a
 * code into words, so the audit screen never shows a raw translation key for
 * an action someone forgot to label: an unlabelled action falls back to its
 * code, which is at least searchable.
 */
final class AuditActions
{
    public static function label(string $action): string
    {
        return self::translate('audit_logs.actions.'.$action) ?? $action;
    }

    public static function targetLabel(string $type): string
    {
        return self::translate('audit_logs.targets.'.$type) ?? $type;
    }

    /**
     * The family an action belongs to, which decides its chip colour and
     * icon. The colours themselves live in the Blade view: Tailwind only
     * scans templates, so a class named here would never be generated.
     */
    public static function kind(string $action): string
    {
        return match (true) {
            in_array($action, ['login', 'logout'], true) => $action,
            in_array($action, ['download', 'export'], true) => $action,
            $action === 'export_bulk' => 'export',
            str_contains($action, 'geometry') => 'geometry',
            str_ends_with($action, '.create'), str_ends_with($action, '.upload'), $action === 'create_user' => 'create',
            str_ends_with($action, '.update'), in_array($action, ['edit_user', 'activate_user', 'deactivate_user'], true) => 'update',
            str_ends_with($action, '.archive') => 'archive',
            str_ends_with($action, '.restore') => 'restore',
            str_ends_with($action, '.delete'), $action === 'delete_user' => 'delete',
            str_ends_with($action, '.approve') => 'approve',
            str_ends_with($action, '.reject') => 'reject',
            str_contains($action, 'status') => 'update',
            default => 'other',
        };
    }

    /**
     * Every action that actually occurs in the log, for the filter dropdown —
     * read from the data rather than hand-listed, so a new kind of write shows
     * up as a filter the day it is first recorded.
     *
     * @return list<string>
     */
    public static function recorded(): array
    {
        return AuditLog::query()
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn (mixed $action): string => (string) $action)
            ->values()
            ->all();
    }

    /**
     * Recorded actions whose label or code contains the search term, so
     * searching «أرشفة» finds `parcel.archive` as well as `deed.archive`.
     *
     * @return list<string>
     */
    public static function matching(string $term): array
    {
        $needle = mb_strtolower(trim($term));

        if ($needle === '') {
            return [];
        }

        return array_values(array_filter(
            self::recorded(),
            fn (string $action): bool => str_contains(mb_strtolower(self::label($action)), $needle)
                || str_contains(mb_strtolower($action), $needle)
        ));
    }

    private static function translate(string $key): ?string
    {
        if (! Lang::has($key)) {
            return null;
        }

        $value = __($key);

        // A code that is a prefix of others (`parcel`) resolves to an array.
        return is_string($value) ? $value : null;
    }
}
