@props(['frontage', 'geojson' => null])

{{-- The lot seen from above in its real shape: streets drawn along the edges
     that face one, with their widths, and each side's length on its edge.
     North is up; dir=ltr keeps east on the right regardless of page direction. --}}
@php
    $streets = $frontage['streets'];
    $dims = $frontage['dims'];
    $outline = \App\Support\LotOutline::fit($geojson, 62, 42, 116, 116, $dims);
    $points = collect($outline['points'])->map(fn ($p) => $p[0].','.$p[1])->implode(' ');
    $roadGap = 13;
    $labelGap = 9;
@endphp
<svg viewBox="0 0 240 200" dir="ltr" {{ $attributes->merge(['class' => 'w-full']) }} role="img" aria-label="{{ __('portal.frontage_title') }}">
    {{-- streets, one straight road along the outside of each side facing one --}}
    @foreach ($streets as $side => $width)
        @if ($anchor = $outline['anchors'][$side] ?? null)
            <line x1="{{ $anchor['x1'] + $anchor['nx'] * $roadGap }}" y1="{{ $anchor['y1'] + $anchor['ny'] * $roadGap }}"
                  x2="{{ $anchor['x2'] + $anchor['nx'] * $roadGap }}" y2="{{ $anchor['y2'] + $anchor['ny'] * $roadGap }}"
                  stroke-width="15" stroke-linecap="round" class="stroke-primary/80 dark:stroke-primary-fixed-dim/40" />
        @endif
    @endforeach
    @foreach ($streets as $side => $width)
        @if ($anchor = $outline['anchors'][$side] ?? null)
            @php $tx = $anchor['x'] + $anchor['nx'] * $roadGap; $ty = $anchor['y'] + $anchor['ny'] * $roadGap; @endphp
            <text x="{{ $tx }}" y="{{ $ty }}" text-anchor="middle" dominant-baseline="middle" font-size="9" class="fill-white"
                  transform="rotate({{ $anchor['angle'] }} {{ $tx }} {{ $ty }})">
                {{ __('portal.street') }}{{ $width ? ' '.rtrim(rtrim(number_format($width, 1), '0'), '.').' '.__('portal.metre_short') : '' }}
            </text>
        @endif
    @endforeach

    {{-- the lot --}}
    <polygon points="{{ $points }}" stroke-linejoin="round" class="fill-secondary/15 stroke-secondary" stroke-width="2" />
    @foreach ($outline['edges'] as $edge)
        @if (array_key_exists($edge['side'], $streets))
            <line x1="{{ $edge['x1'] }}" y1="{{ $edge['y1'] }}" x2="{{ $edge['x2'] }}" y2="{{ $edge['y2'] }}" class="stroke-tertiary-container" stroke-width="5" stroke-linecap="round" />
        @endif
    @endforeach
    @foreach ($dims as $side => $length)
        @if ($length !== null && ($anchor = $outline['anchors'][$side] ?? null))
            @php $dx = $anchor['x'] - $anchor['nx'] * $labelGap; $dy = $anchor['y'] - $anchor['ny'] * $labelGap; @endphp
            <text x="{{ $dx }}" y="{{ $dy }}" text-anchor="middle" dominant-baseline="middle" font-size="10" class="fill-on-surface dark:fill-white"
                  transform="rotate({{ $anchor['angle'] }} {{ $dx }} {{ $dy }})">{{ rtrim(rtrim(number_format($length, 2), '0'), '.') }} {{ __('portal.metre_short') }}</text>
        @endif
    @endforeach

    {{-- north arrow --}}
    <g transform="translate(222 176)">
        <polygon points="0,-12 5,4 0,0 -5,4" class="fill-secondary" />
        <text x="0" y="16" text-anchor="middle" font-size="9" class="fill-secondary">N</text>
    </g>
</svg>
