{{-- Saudi Arabia drawn as a plat: the outline, a survey grid clipped to it and
     the main cities. Decoration only (aria-hidden). $id keeps the pattern and
     clip ids unique when two maps share a page; $animate traces the border in. --}}
@props(['id' => 'ksa', 'cell' => 12, 'animate' => false, 'cities' => true])
@php
    [$w, $h] = \App\Support\SaudiOutline::size();
    $path = \App\Support\SaudiOutline::path();
@endphp
<svg viewBox="-4 -4 {{ $w + 8 }} {{ $h + 8 }}" {{ $attributes->merge(['class' => 'pointer-events-none']) }} aria-hidden="true">
    <defs>
        <pattern id="{{ $id }}-grid" width="{{ $cell }}" height="{{ $cell * 0.75 }}" patternUnits="userSpaceOnUse">
            <path d="M{{ $cell }} 0H0V{{ $cell * 0.75 }}" fill="none" stroke="currentColor" stroke-width="0.4" />
        </pattern>
        <clipPath id="{{ $id }}-clip"><path d="{{ $path }}" /></clipPath>
    </defs>

    <rect x="0" y="0" width="{{ $w }}" height="{{ $h }}" fill="url(#{{ $id }}-grid)" clip-path="url(#{{ $id }}-clip)" opacity="0.7" />
    <path d="{{ $path }}" fill="currentColor" fill-opacity="0.04" stroke="currentColor" stroke-width="1" stroke-linejoin="round"
          @if ($animate) pathLength="1" class="ksa-trace" @endif />

    @if ($cities)
        @foreach (\App\Support\SaudiOutline::cities() as $name => [$cx, $cy])
            @if ($name === 'riyadh')
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="5" fill="#e6c364" opacity="0.25" class="ksa-pulse" />
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="2.2" fill="#e6c364" />
            @else
                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="1.4" fill="currentColor" opacity="0.8" />
            @endif
        @endforeach
    @endif
</svg>

@once
    <style>
        .ksa-trace { stroke-dasharray: 1; stroke-dashoffset: 1; animation: ksa-trace 3.2s ease-out forwards; }
        @keyframes ksa-trace { to { stroke-dashoffset: 0; } }
        .ksa-pulse { transform-box: fill-box; transform-origin: center; animation: ksa-pulse 2.4s ease-out infinite; }
        @keyframes ksa-pulse { 0% { transform: scale(0.6); opacity: 0.5; } 100% { transform: scale(2.4); opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .ksa-trace, .ksa-pulse { animation: none; stroke-dashoffset: 0; } }
    </style>
@endonce
