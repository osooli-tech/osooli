<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PdfArabicText;
use Tests\TestCase;

/**
 * dompdf applies neither the Unicode bidi algorithm nor Arabic letter
 * shaping, so any raw Arabic string it renders comes out character-reversed.
 * This was found live in production (the parcels list PDF export) before
 * being fixed here — these guard against it recurring.
 */
class PdfArabicTextTest extends TestCase
{
    public function test_a_pure_latin_string_passes_through_unshaped(): void
    {
        $this->assertSame('Parcel 164-623', PdfArabicText::shape('Parcel 164-623'));
    }

    public function test_null_and_empty_strings_pass_through(): void
    {
        $this->assertNull(PdfArabicText::shape(null));
        $this->assertSame('', PdfArabicText::shape(''));
    }

    public function test_an_arabic_string_is_shaped_into_a_different_byte_sequence(): void
    {
        // The exact shaped output is an implementation detail of the shaping
        // library; what must hold is that it changed at all (dompdf renders
        // the unshaped original character-reversed) and it is not empty.
        $shaped = PdfArabicText::shape('الأمير فيصل بن مساعد بن عبدالرحمن آل سعود');

        $this->assertNotNull($shaped);
        $this->assertNotSame('الأمير فيصل بن مساعد بن عبدالرحمن آل سعود', $shaped);
    }

    public function test_a_shadda_before_a_lam_alef_ligature_does_not_corrupt_it(): void
    {
        // Regression: شّ before لا) in الملّاك) produced a spurious extra alef
        // from the shaping library. Diacritics are stripped first, and the
        // result must match the diacritic-free spelling shaped on its own.
        $withShadda = PdfArabicText::shape('الملّاك');
        $withoutShadda = PdfArabicText::shape('الملاك');

        $this->assertSame($withoutShadda, $withShadda);
    }

    public function test_render_wraps_the_shaped_text_in_a_left_to_right_span(): void
    {
        $html = PdfArabicText::render('أرض');

        $this->assertStringStartsWith('<span dir="ltr">', $html);
        $this->assertStringEndsWith('</span>', $html);
    }

    public function test_render_of_an_empty_value_produces_no_markup(): void
    {
        $this->assertSame('', PdfArabicText::render(null));
        $this->assertSame('', PdfArabicText::render(''));
    }
}
