<?php

declare(strict_types=1);

namespace App\Support;

use ArPHP\I18N\Arabic;

/**
 * dompdf does not apply the Unicode bidi algorithm or Arabic letter shaping,
 * so any Arabic string handed to it renders character-reversed — this affects
 * every PDF export in the project (confirmed on the already-shipped parcels
 * list export, not only new reports).
 *
 * The fix is to pre-shape the string into its final visual glyph order before
 * dompdf ever sees it, then render that result as a plain left-to-right run.
 * Mixed Arabic/Latin/numeric text (e.g. "صك رقم 511604001155") is handled
 * correctly by the shaping library itself — nothing extra is needed here for
 * that case.
 */
class PdfArabicText
{
    private static ?Arabic $shaper = null;

    /** Shapes the string only if it actually contains Arabic; a pure-Latin
     *  string is returned untouched. */
    public static function shape(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        if (! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        self::$shaper ??= new Arabic;

        // Tashkeel (harakat, tanween, shadda) is rarely present in official
        // Saudi documents and reads fine without it; stripped before shaping
        // because a shadda immediately before a lam-alef ligature (e.g. the
        // word الملّاك) corrupts the ligature — an upstream shaping bug, not
        // something worth working around per diacritic combination.
        $text = (string) preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $text);

        return self::$shaper->utf8Glyphs($text, 900, false);
    }

    /** HTML-escaped and wrapped for direct output with {!! !!} in a PDF view. */
    public static function render(?string $text): string
    {
        $shaped = self::shape($text);

        if ($shaped === null || $shaped === '') {
            return '';
        }

        return '<span dir="ltr">'.e($shaped).'</span>';
    }
}
