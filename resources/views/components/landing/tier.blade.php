@props([
    'name',
    'note',
    'price',
    'period' => null,      // omitted on the "talk to us" tier
    'features' => [],
    'cta',
    'ctaStyle' => 'wire-d', // 'gold' on the highlighted tier
    'featured' => false,
])

{{-- One pricing plan. The featured tier inverts to navy and carries the flag. --}}
<div class="tier @if ($featured) tier--hi @endif">
    @if ($featured)
        <span class="tier-flag">{{ __('landing.pricing_flag') }}</span>
    @endif

    <h3 class="tier-name">{{ $name }}</h3>
    <p class="tier-note">{{ $note }}</p>

    <p class="tier-price">
        {{ $price }}@if ($period)<small> {{ $period }}</small>@endif
    </p>

    <ul>
        @foreach ($features as $feature)
            <li>
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
                </svg>
                {{ $feature }}
            </li>
        @endforeach
    </ul>

    <a class="btn btn--{{ $ctaStyle }}" href="#contact">{{ $cta }}</a>
</div>
