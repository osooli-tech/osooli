@props(['frontage'])

{{-- The lot seen from above: streets drawn along the sides that face one,
     with their widths, and each side's length. North is up; dir=ltr keeps
     east on the right regardless of page direction. --}}
@php
    $streets = $frontage['streets'];
    $dims = $frontage['dims'];
    $sideLine = ['n' => [60, 40, 180, 40], 's' => [60, 160, 180, 160], 'e' => [180, 40, 180, 160], 'w' => [60, 40, 60, 160]];
    $road = ['n' => [40, 10, 200, 26], 's' => [40, 174, 200, 190], 'e' => [194, 20, 210, 180], 'w' => [30, 20, 46, 180]];
    $dimPos = ['n' => [120, 54], 's' => [120, 152], 'e' => [168, 104], 'w' => [72, 104]];
    $roadText = ['n' => [120, 21], 's' => [120, 185], 'e' => [202, 100], 'w' => [38, 100]];
@endphp
<svg viewBox="0 0 240 200" dir="ltr" {{ $attributes->merge(['class' => 'w-full']) }} role="img" aria-label="{{ __('portal.frontage_title') }}">
    {{-- streets --}}
    @foreach ($streets as $side => $width)
        @php [$x1, $y1, $x2, $y2] = $road[$side]; @endphp
        <rect x="{{ $x1 }}" y="{{ $y1 }}" width="{{ $x2 - $x1 }}" height="{{ $y2 - $y1 }}" rx="4" class="fill-primary/80 dark:fill-primary-fixed-dim/40" />
        @php [$tx, $ty] = $roadText[$side]; $vertical = in_array($side, ['e', 'w'], true); @endphp
        <text x="{{ $tx }}" y="{{ $ty }}" text-anchor="middle" dominant-baseline="middle" font-size="9" class="fill-white"
              @if ($vertical) transform="rotate(-90 {{ $tx }} {{ $ty }})" @endif>
            {{ __('portal.street') }}{{ $width ? ' '.rtrim(rtrim(number_format($width, 1), '0'), '.').' '.__('portal.metre_short') : '' }}
        </text>
    @endforeach

    {{-- the lot --}}
    <rect x="60" y="40" width="120" height="120" rx="3" class="fill-secondary/15 stroke-secondary" stroke-width="2" />
    @foreach ($sideLine as $side => [$x1, $y1, $x2, $y2])
        @if (array_key_exists($side, $streets))
            <line x1="{{ $x1 }}" y1="{{ $y1 }}" x2="{{ $x2 }}" y2="{{ $y2 }}" class="stroke-tertiary-container" stroke-width="5" stroke-linecap="round" />
        @endif
        @if ($dims[$side] !== null)
            @php [$dx, $dy] = $dimPos[$side]; $vertical = in_array($side, ['e', 'w'], true); @endphp
            <text x="{{ $dx }}" y="{{ $dy }}" text-anchor="middle" dominant-baseline="middle" font-size="10" class="fill-on-surface dark:fill-white"
                  @if ($vertical) transform="rotate(-90 {{ $dx }} {{ $dy }})" @endif>{{ rtrim(rtrim(number_format($dims[$side], 2), '0'), '.') }} {{ __('portal.metre_short') }}</text>
        @endif
    @endforeach

    {{-- north arrow --}}
    <g transform="translate(222 176)">
        <polygon points="0,-12 5,4 0,0 -5,4" class="fill-secondary" />
        <text x="0" y="16" text-anchor="middle" font-size="9" class="fill-secondary">N</text>
    </g>
</svg>
