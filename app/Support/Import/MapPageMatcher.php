<?php

declare(strict_types=1);

namespace App\Support\Import;

/**
 * The parcel and plan numbers a map-series page names, read from its text.
 *
 * ArcGIS Pro exports a map series with the page's dynamic text as real text
 * — "الخريطة المساحية للقطعة رقم 131 من المخطط رقم 623" — but in the order
 * the PDF happens to store it, which for Arabic is often reversed: a number
 * may sit before its label or after it, and not always beside it. So this
 * does not trust any one reading. It scores every number found near the
 * labels that introduce a parcel or a plan — more for the fixed phrases
 * ArcGIS templates use — and the caller keeps the best-scoring pair that is
 * actually a parcel on record. A deed number or a year near a label scores
 * too, but it is not a parcel of that plan, so it falls away.
 */
final class MapPageMatcher
{
    private const PARCEL_LABELS = ['للقطعة رقم', 'رقم القطعة', 'القطعة رقم', 'Parcel No'];

    private const PLAN_LABELS = ['المخطط رقم', 'رقم المخطط', 'مخطط رقم', 'Plan No'];

    /** Characters either side of a label searched for its number. */
    private const WINDOW = 30;

    /**
     * Whole phrases that name the page's own parcel and plan, in either
     * reading order — worth far more than a number merely nearby.
     */
    private const STRONG = [
        'parcel' => [
            '/(\d{1,6})\s*\)?\s*الخريطة (?:المساحية|الكنتورية) للقطعة رقم/u',
            '/للقطعة رقم\s*\(?\s*(\d{1,6})/u',
        ],
        'plan' => [
            '/(\d{1,6})\s*\)?\s*من المخطط رقم/u',
            '/من المخطط رقم\s*\(?\s*(\d{1,6})/u',
        ],
    ];

    /**
     * @return array{parcel: array<int|string, int>, plan: array<int|string, int>} number => score
     */
    public static function scores(string $text): array
    {
        // Presentation forms (ﺔﻴﻋ…) to ordinary letters, one kind of space.
        $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        // The text as stored, and with each run of Arabic letters turned
        // round — some readers hand Arabic back in visual order.
        $best = ['parcel' => [], 'plan' => []];
        foreach ([$text, self::reverseArabicRuns($text)] as $variant) {
            $scores = ['parcel' => self::near($variant, self::PARCEL_LABELS), 'plan' => self::near($variant, self::PLAN_LABELS)];

            foreach (self::STRONG as $kind => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $variant, $m) > 0) {
                        foreach ($m[1] as $number) {
                            $scores[$kind][$number] = ($scores[$kind][$number] ?? 0) + 10;
                        }
                    }
                }
            }

            foreach ($scores as $kind => $numbers) {
                foreach ($numbers as $number => $score) {
                    $best[$kind][(string) $number] = max($best[$kind][(string) $number] ?? 0, $score);
                }
            }
        }

        return $best;
    }

    private static function reverseArabicRuns(string $text): string
    {
        return (string) preg_replace_callback(
            '/[\x{0600}-\x{06FF} ]{2,}/u',
            static fn (array $m): string => implode('', array_reverse(mb_str_split($m[0]))),
            $text
        );
    }

    /**
     * Numbers within a few characters of any of the labels, each scored by
     * how often it stands there.
     *
     * @param  list<string>  $labels
     * @return array<int|string, int>
     */
    private static function near(string $text, array $labels): array
    {
        $counts = [];
        foreach ($labels as $label) {
            $offset = 0;
            while (($pos = mb_stripos($text, $label, $offset)) !== false) {
                $before = mb_substr($text, max(0, $pos - self::WINDOW), min($pos, self::WINDOW));
                $after = mb_substr($text, $pos + mb_strlen($label), self::WINDOW);

                foreach ([$before, $after] as $side) {
                    if (preg_match_all('/(?<![\d.\/])(\d{1,6})(?![\d.\/])/u', $side, $m) > 0) {
                        foreach ($m[1] as $number) {
                            $counts[$number] = ($counts[$number] ?? 0) + 1;
                        }
                    }
                }
                $offset = $pos + 1;
            }
        }

        return $counts;
    }
}
