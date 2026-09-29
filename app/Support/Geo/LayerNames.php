<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Whether two layer names mean the same layer: "Buildings" and "Building",
 * "المباني" and "مباني", "Water_Wells" and "water wells". Used to warn,
 * when a geodatabase brings a layer, that one of a similar name is already
 * on the map — so it is added to that one on purpose, or kept apart on
 * purpose, not by a slip of spelling.
 */
final class LayerNames
{
    /** The names projects and buildings are kept under as custom layers. */
    public const LAYER_NAMES = [
        'projects' => 'المشاريع',
        'buildings' => 'المباني',
    ];

    /** Names at or above this score are called similar. */
    public const SIMILAR = 0.8;

    /**
     * The layers the platform knows, by role, under the names they tend to
     * carry. Only parcels are a role of their own; projects and buildings
     * are custom layers, recognised by name so that "Buildings" in a file
     * finds the «المباني» layer on the map.
     *
     * @var array<string, list<string>>
     */
    public const BUILT_IN = [
        'parcels' => ['Parcel', 'Parcels', 'Land', 'القطع', 'قطع', 'الأراضي', 'أراضي'],
        'projects' => ['Project', 'Projects', 'المشاريع', 'مشاريع', 'مشروع'],
        'buildings' => ['Building', 'Buildings', 'المباني', 'مباني', 'مبنى'],
    ];

    /** A name reduced to what distinguishes it: case, spacing, hamza and "ال" set aside. */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(['أ', 'إ', 'آ', 'ٱ', 'ة', 'ى', 'ـ'], ['ا', 'ا', 'ا', 'ا', 'ه', 'ي', ''], $name);
        // Arabic diacritics.
        $name = (string) preg_replace('/[\x{064B}-\x{0652}]/u', '', $name);

        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_map(static function (string $word): string {
            if (mb_strlen($word) > 3 && str_starts_with($word, 'ال')) {
                $word = mb_substr($word, 2);
            }
            // English plural: "buildings" is "building".
            if (mb_strlen($word) > 3 && preg_match('/^[a-z0-9]+s$/', $word) === 1 && ! str_ends_with($word, 'ss')) {
                $word = substr($word, 0, -1);
            }

            return $word;
        }, $words);

        return implode('', $words);
    }

    /** 1 for the same name, down to 0 for nothing alike. */
    public static function similarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }
        if ($a === $b) {
            return 1.0;
        }

        $shorter = min(mb_strlen($a), mb_strlen($b));
        if ($shorter >= 3 && (str_contains($a, $b) || str_contains($b, $a))) {
            return 0.85;
        }

        return 1 - self::distance($a, $b) / max(mb_strlen($a), mb_strlen($b));
    }

    /**
     * The candidates similar to $name, most similar first.
     *
     * @param  list<array<string, mixed>>  $candidates  each with a 'name'
     * @return list<array<string, mixed>> the same, each with a 'score' added
     */
    public static function similarTo(string $name, array $candidates): array
    {
        $found = [];
        foreach ($candidates as $candidate) {
            $score = self::similarity($name, (string) $candidate['name']);
            if ($score >= self::SIMILAR) {
                $found[] = $candidate + ['score' => round($score, 2)];
            }
        }
        usort($found, static fn (array $x, array $y): int => $y['score'] <=> $x['score']);

        return $found;
    }

    /** The built-in role a name stands for, if it plainly is one. */
    public static function builtInRole(string $name): ?string
    {
        foreach (self::BUILT_IN as $role => $names) {
            foreach ($names as $known) {
                if (self::similarity($name, $known) >= 0.9) {
                    return $role;
                }
            }
        }

        return null;
    }

    /** Levenshtein distance by character, not byte — Arabic is multi-byte. */
    private static function distance(string $a, string $b): int
    {
        $x = mb_str_split($a);
        $y = mb_str_split($b);
        $previous = range(0, count($y));

        foreach ($x as $i => $charA) {
            $current = [$i + 1];
            foreach ($y as $j => $charB) {
                $current[$j + 1] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($charA === $charB ? 0 : 1),
                );
            }
            $previous = $current;
        }

        return $previous[count($y)];
    }
}
