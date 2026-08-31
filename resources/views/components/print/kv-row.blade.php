@props(['label', 'value'])
{{-- dompdf renders <td> in strict DOM order regardless of dir="rtl" — it does
     not reverse table columns the way a browser does. So for Arabic, value
     has to come first in the markup for the label to land on the right.

     Shaping runs unconditionally, not just under the Arabic locale: a value
     can be Arabic-origin data (an owner's name, a district name) even on an
     English-locale page, and PdfArabicText::shape() already no-ops on a
     pure-Latin string, so shaping it here is always safe. --}}
@php
    $shapedLabel = \App\Support\PdfArabicText::render($label);
    $shapedValue = \App\Support\PdfArabicText::render((string) $value);
@endphp
@if (app()->isLocale('ar'))
<tr><td class="value">{!! $shapedValue !!}</td><td class="label">{!! $shapedLabel !!}</td></tr>
@else
<tr><td class="label">{!! $shapedLabel !!}</td><td class="value">{!! $shapedValue !!}</td></tr>
@endif
