<?php

declare(strict_types=1);

namespace App\Support\Import;

/** Spellings of one Arabic name reduced to one form, for matching only. */
final class Normalise
{
    /**
     * No tatweel or diacritics, one alif, ه for ة, ي for ى, single spaces,
     * and no leading «حي» or «منطقة».
     */
    public static function arabic(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text) ?? $text;
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي']);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        $text = preg_replace('/^(حي|منطقه|المنطقه)\s+/u', '', $text) ?? $text;

        return mb_strtolower($text);
    }
}
