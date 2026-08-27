<section class="band band--navy-grad">
    <div class="inner hero">
        <div>
            <span class="eyebrow">{{ __('landing.hero_eyebrow') }}</span>

            <h1 class="h-display">
                {{ __('landing.hero_title_line1') }}<br>
                <x-landing.mark>{{ __('landing.hero_title_mark') }}</x-landing.mark>
            </h1>

            <p class="lede" style="margin-top:22px">{{ __('landing.hero_lede') }}</p>

            <div class="hero-actions">
                <a class="btn btn--go" href="#contact">
                    {{ __('landing.hero_cta_primary') }}
                    <x-landing.arrow />
                </a>
                <a class="btn btn--wire-l" href="#app">
                    <svg class="ar" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                        <path d="M7 1.5h10A2.5 2.5 0 0 1 19.5 4v16a2.5 2.5 0 0 1-2.5 2.5H7A2.5 2.5 0 0 1 4.5 20V4A2.5 2.5 0 0 1 7 1.5m5 16.8a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4"/>
                    </svg>
                    {{ __('landing.hero_cta_secondary') }}
                </a>
            </div>

            {{-- Headline figures. Swap for live counts once the marketing site
                 is allowed to read from the platform. --}}
            <div class="hero-stats">
                <div class="stat">
                    <b>{{ $stats['parcels'] }}</b>
                    <span>{{ __('landing.hero_stat_parcels') }}</span>
                </div>
                <div class="stat">
                    <b>{{ $stats['area'] }}</b>
                    <span>{{ __('landing.hero_stat_area') }}</span>
                </div>
                <div class="stat">
                    <b>{{ $stats['updated'] }}</b>
                    <span>{{ __('landing.hero_stat_updated') }}</span>
                </div>
            </div>
        </div>

        <div>@include('landing.art.hero')</div>
    </div>
</section>
