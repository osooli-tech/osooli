{{--
    The landing page's accent: a survey dimension line with end ticks under a
    key phrase, the way a distance is called out on a plat.

    One viewBox serves every phrase length — preserveAspectRatio="none" stretches
    the line to the phrase, and vector-effect="non-scaling-stroke" keeps the
    weight from stretching with it.

    Usage: <x-landing.mark>قرارات عقارية أفضل</x-landing.mark>
--}}
<span class="mark">{{ $slot }}<svg viewBox="0 0 100 12" preserveAspectRatio="none" aria-hidden="true" focusable="false">
    <path d="M2 7 H98" vector-effect="non-scaling-stroke"/>
    <path class="tick" d="M2 2 V12" vector-effect="non-scaling-stroke"/>
    <path class="tick" d="M98 2 V12" vector-effect="non-scaling-stroke"/>
</svg></span>
