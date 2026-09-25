<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Models\Owner;
use Illuminate\Support\Facades\DB;

/**
 * Decides, for an owner in an import file, whether they are already in the
 * system — and when it cannot be sure, which existing owners they might be.
 *
 *  - Same national ID: the same person. Linked; any differing contact
 *    details are shown as changes.
 *  - No national ID, but exactly one owner with the same name and phone:
 *    the same person too.
 *  - Otherwise, owners who are *probably* the same are offered as
 *    candidates and a person decides: a national ID one digit away (the
 *    usual typing slip) or with two digits swapped, the same phone, the
 *    same name, or a name within a letter or two.
 *  - Nothing close: a new owner.
 *
 * Every owner is held in memory once per analysis — a few megabytes even at
 * a hundred thousand owners — so no candidate search costs a query.
 */
final class OwnerMatcher
{
    private const MAX_CANDIDATES = 5;

    /** Bytes of edit distance still called "the same name" (an Arabic letter is two). */
    private const NAME_DISTANCE = 4;

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var array<string, int> */
    private array $byNid = [];

    /** @var array<string, list<int>> */
    private array $byPhone = [];

    /** @var array<string, list<int>> */
    private array $byName = [];

    /** @var array<string, list<int>> first word of the name => owners */
    private array $byFirstWord = [];

    public function __construct()
    {
        foreach (DB::table('owners')->select(['id', 'name', 'national_id', 'phone', 'email', 'whatsapp', 'phone_normalized', 'deleted_at'])->orderBy('id')->cursor() as $owner) {
            $id = (int) $owner->id;
            $this->rows[$id] = (array) $owner;

            if ($owner->national_id !== null && $owner->national_id !== '') {
                $this->byNid[(string) $owner->national_id] = $id;
            }
            if ($owner->phone_normalized !== null && $owner->phone_normalized !== '') {
                $this->byPhone[(string) $owner->phone_normalized][] = $id;
            }

            $name = Normalise::arabic((string) $owner->name);
            $this->byName[$name][] = $id;
            $this->byFirstWord[strtok($name, ' ') ?: $name][] = $id;
        }
    }

    /**
     * The key that makes two mentions of one owner in a file the same owner.
     *
     * @param  array<string, mixed>  $owner
     */
    public static function key(array $owner): string
    {
        return $owner['national_id'] !== null
            ? 'nid:'.$owner['national_id']
            : 'name:'.Normalise::arabic((string) $owner['name']).'|'.Owner::normalisePhone((string) $owner['phone']);
    }

    /** @return array<string, mixed>|null */
    public function row(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    /**
     * @param  array<string, mixed>  $owner  name, national_id, phone, …
     * @return array{match: int|null, candidates: list<array{id: int, label: string, reasons: list<string>}>}
     */
    public function match(array $owner): array
    {
        $nid = $owner['national_id'];
        $phone = $owner['phone'] === null ? '' : Owner::normalisePhone((string) $owner['phone']);
        $name = Normalise::arabic((string) $owner['name']);

        $exact = $nid === null ? null : $this->ownerWithNid($nid);
        if ($exact !== null) {
            return ['match' => $exact, 'candidates' => []];
        }

        $reasons = [];

        if ($nid !== null) {
            foreach (self::nidNeighbours($nid) as $near) {
                $id = $this->ownerWithNid($near);
                if ($id !== null) {
                    $reasons[$id][] = 'nid_close';
                }
            }
        }

        if ($phone !== '') {
            foreach ($this->byPhone[$phone] ?? [] as $id) {
                $reasons[$id][] = 'same_phone';
            }
        }

        if ($name !== '') {
            foreach ($this->byName[$name] ?? [] as $id) {
                $reasons[$id][] = 'same_name';
            }

            $bucket = $this->byFirstWord[strtok($name, ' ') ?: $name] ?? [];
            if (count($bucket) <= 2000) {
                foreach ($bucket as $id) {
                    $other = Normalise::arabic((string) $this->rows[$id]['name']);
                    if ($other !== $name && levenshtein($name, $other) <= self::NAME_DISTANCE) {
                        $reasons[$id][] = 'close_name';
                    }
                }
            }
        }

        // No national ID to go on, and one owner with this very name and
        // phone: that is the same person, not a question.
        if ($nid === null && $phone !== '') {
            $exact = array_keys(array_filter($reasons, static fn (array $r): bool => in_array('same_phone', $r, true) && in_array('same_name', $r, true)));
            if (count($exact) === 1) {
                return ['match' => $exact[0], 'candidates' => []];
            }
        }

        // Strongest evidence first.
        $weight = ['nid_close' => 4, 'same_phone' => 3, 'same_name' => 2, 'close_name' => 1];
        uksort($reasons, static fn (int $a, int $b): int => array_sum(array_map(fn ($r) => $weight[$r], $reasons[$b]))
            <=> array_sum(array_map(fn ($r) => $weight[$r], $reasons[$a])));

        $candidates = [];
        foreach (array_slice($reasons, 0, self::MAX_CANDIDATES, true) as $id => $why) {
            $row = $this->rows[$id];
            $candidates[] = [
                'id' => $id,
                'label' => trim($row['name'].' — '.($row['national_id'] ?? '—').' — '.($row['phone'] ?? '—')),
                'reasons' => array_values(array_unique($why)),
            ];
        }

        return ['match' => null, 'candidates' => $candidates];
    }

    private function ownerWithNid(string $nid): ?int
    {
        return $this->byNid[$nid] ?? null;
    }

    /**
     * National IDs one keystroke away: one digit changed, or two
     * neighbouring digits swapped.
     *
     * @return list<string>
     */
    private static function nidNeighbours(string $nid): array
    {
        if (! ctype_digit($nid) || strlen($nid) > 15) {
            return [];
        }

        $near = [];
        $length = strlen($nid);

        for ($i = 0; $i < $length; $i++) {
            for ($digit = 0; $digit <= 9; $digit++) {
                if ((string) $digit !== $nid[$i]) {
                    $near[] = substr_replace($nid, (string) $digit, $i, 1);
                }
            }
            if ($i < $length - 1 && $nid[$i] !== $nid[$i + 1]) {
                $near[] = substr($nid, 0, $i).$nid[$i + 1].$nid[$i].substr($nid, $i + 2);
            }
        }

        return $near;
    }
}
