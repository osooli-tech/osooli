<?php

declare(strict_types=1);

namespace App\Support\Import;

/**
 * One feature of an import file, read into the shape the analyser works on.
 *
 * Accepts what our export writes (nested `deed`, `parcel`, `owners` …) and,
 * for a file built by hand or reshaped by GIS software, the flat columns
 * alongside them (`deed_no`, `parcel_geo_id`, `district` …). Nested JSON
 * that a GIS program turned into a string is decoded back.
 *
 * A part missing from the feature is null and means "leave it alone"; an
 * empty value inside a part never erases what the database holds.
 */
final class ImportRecord
{
    /** @var array<string, mixed> */
    public array $deed = [];

    /** @var array<string, mixed> */
    public array $parcel = [];

    public ?string $planNo = null;

    /** @var array{region: ?string, city: ?string, district: ?string} */
    public array $location = ['region' => null, 'city' => null, 'district' => null];

    /** @var list<array<string, mixed>>|null */
    public ?array $owners = null;

    /** @var array<string, mixed>|null */
    public ?array $boundary = null;

    /** @var list<array<string, mixed>>|null */
    public ?array $surveyDecisions = null;

    /** @var array<string, mixed>|null */
    public ?array $geometry = null;

    /** @var list<string> fields whose flat column and nested copy disagree */
    public array $conflicts = [];

    /** Whether the feature says anything about a deed; if not, it is a parcel alone. */
    public bool $hasDeed = true;

    /** The engineering office named in the boundary, if any. */
    public ?string $engineeringOffice = null;

    /** @param  array<string, mixed>  $feature */
    public static function fromFeature(array $feature): self
    {
        $record = new self;
        $p = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        foreach ($p as $key => $value) {
            $p[$key] = self::decodeJsonString($value);
        }

        $deed = is_array($p['deed'] ?? null) ? $p['deed'] : [];
        $parcel = is_array($p['parcel'] ?? null) ? $p['parcel'] : [];
        $location = is_array($parcel['location'] ?? null) ? $parcel['location'] : [];

        // The flat column is what a GIS attribute table shows and what
        // people edit there, so it wins over the nested copy — noted when
        // the two disagree.
        $pick = static function (string $field, string $flat, mixed $nested) use ($p, $record): mixed {
            if (! array_key_exists($flat, $p)) {
                return $nested;
            }
            $value = $p[$flat];
            if ($nested !== null && $value !== null && ! is_array($value) && (string) $nested !== (string) $value) {
                $record->conflicts[] = $field;
            }

            return $value ?? $nested;
        };

        $record->deed = [
            'id' => self::int($pick('deed_id', 'deed_id', $deed['id'] ?? null)),
            'deed_no' => self::str($pick('deed_no', 'deed_no', $deed['deed_no'] ?? null)),
            'deed_date_hijri' => self::str($pick('deed_date_hijri', 'deed_date_hijri', $deed['deed_date_hijri'] ?? null)),
            'deed_area' => $pick('deed_area', 'deed_area', $deed['deed_area'] ?? null),
            'deed_status' => self::str($pick('deed_status', 'deed_status', $deed['deed_status'] ?? null)),
            'deed_class' => self::str($pick('deed_class', 'deed_class', $deed['deed_class'] ?? null)),
        ];

        $record->parcel = [
            'geo_id' => self::str($pick('geo_id', 'parcel_geo_id', $parcel['geo_id'] ?? null)),
            'parcel_no' => self::str($pick('parcel_no', 'parcel_no', $parcel['parcel_no'] ?? null)),
            'asset_type' => self::str($parcel['asset_type'] ?? null),
            'land_transaction' => self::str($parcel['land_transaction'] ?? null),
            'allocation_method' => self::str($parcel['allocation_method'] ?? null),
            'fall_in' => self::str($parcel['fall_in'] ?? null),
            'm_price' => $parcel['m_price'] ?? null,
            'parcel_price' => $parcel['parcel_price'] ?? null,
            'parent_geo_id' => self::str($pick('parent_geo_id', 'parent_geo_id', $parcel['parent_geo_id'] ?? null)),
        ];

        // A feature with nothing about a deed describes a parcel alone.
        $record->hasDeed = array_filter($record->deed, static fn (mixed $v): bool => $v !== null && $v !== '') !== [];

        $record->planNo = self::str($pick('plan_no', 'plan_no', $parcel['plan']['plan_no'] ?? null));
        $record->location = [
            'region' => self::str($pick('region', 'region', $location['region']['name_ar'] ?? null)),
            'city' => self::str($pick('city', 'city', $location['city']['name_ar'] ?? null)),
            'district' => self::str($pick('district', 'district', $location['district']['name_ar'] ?? null)),
        ];

        if (is_array($p['owners'] ?? null)) {
            $record->owners = array_values(array_filter(array_map(static fn (mixed $o): ?array => is_array($o) ? [
                'name' => self::str($o['name'] ?? null),
                'national_id' => self::str($o['national_id'] ?? null),
                'phone' => self::str($o['phone'] ?? null),
                'email' => self::str($o['email'] ?? null),
                'whatsapp' => self::str($o['whatsapp'] ?? null),
                'ownership_share' => $o['ownership_share'] ?? null,
            ] : null, $p['owners'])));
        }

        if (is_array($p['boundary'] ?? null)) {
            $b = $p['boundary'];
            $record->boundary = [
                'n_border' => self::str($b['north']['border'] ?? null), 'n_dim' => $b['north']['length'] ?? null,
                's_border' => self::str($b['south']['border'] ?? null), 's_dim' => $b['south']['length'] ?? null,
                'e_border' => self::str($b['east']['border'] ?? null), 'e_dim' => $b['east']['length'] ?? null,
                'w_border' => self::str($b['west']['border'] ?? null), 'w_dim' => $b['west']['length'] ?? null,
                'measured_area' => $b['measured_area'] ?? null,
                'survey_date' => self::str($b['survey_date'] ?? null),
                'matches_deed' => self::bool($b['matches_deed'] ?? null),
            ];
            $record->engineeringOffice = self::str($b['engineering_office'] ?? null);
        }

        if (is_array($p['survey_decisions'] ?? null)) {
            $record->surveyDecisions = array_values(array_filter(array_map(static fn (mixed $d): ?array => is_array($d) ? [
                'id' => self::int($d['id'] ?? null),
                'qrar_no' => self::str($d['qrar_no'] ?? null),
                'report_no' => self::str($d['report_no'] ?? null),
                'qrar_source' => self::str($d['qrar_source'] ?? null),
                'folder' => self::str($d['folder'] ?? null),
            ] : null, $p['survey_decisions'])));
        }

        $record->geometry = is_array($feature['geometry'] ?? null) ? $feature['geometry'] : null;

        return $record;
    }

    /** What the review screen calls this record. */
    public function label(): string
    {
        return $this->deed['deed_no'] ?? $this->parcel['geo_id'] ?? '—';
    }

    private static function decodeJsonString(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ($value[0] === '{' || $value[0] === '[')) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }

        return $value;
    }

    public static function str(mixed $value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** true / false from the ways a spreadsheet or GIS writes them; null if unclear. */
    private static function bool(mixed $value): ?bool
    {
        if (is_bool($value) || $value === null) {
            return $value;
        }

        return match (mb_strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'نعم', 'مطابق' => true,
            '0', 'false', 'no', 'لا', 'غير مطابق' => false,
            default => null,
        };
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
