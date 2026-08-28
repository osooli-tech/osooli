@php
    /**
     * The owner app tour. Slides are data so the order can change without
     * touching markup, and so the count stays in one place — the rail, the
     * arrows and the live counter all read from it.
     *
     * Each `image` is a real device screenshot under public/images/app/, stored as
     * WebP: the sources are already JPEG, so PNG would faithfully re-encode the
     * compression artefacts at seven times the weight.
     */
    $slides = [
        ['image' => 'app-home.webp',          'key' => 'home'],
        ['image' => 'app-parcels.webp',       'key' => 'parcels'],
        ['image' => 'app-deeds.webp',         'key' => 'deeds'],
        ['image' => 'app-parcel-detail.webp', 'key' => 'detail'],
        ['image' => 'app-parcel-owners.webp', 'key' => 'owners'],
        ['image' => 'app-map.webp',           'key' => 'map'],
    ];
@endphp

<section class="band band--navy-grad pad" id="app">
    <div class="inner">

        <div class="shead rv">
            <span class="eyebrow">{{ __('landing.app_eyebrow') }}</span>
            <h2 class="h-section">
                {{ __('landing.app_title') }}
                <x-landing.mark>{{ __('landing.app_title_mark') }}</x-landing.mark>
            </h2>
            <p class="lede">{{ __('landing.app_lede') }}</p>
        </div>

        {{-- The track is a scroll-snap strip, so it swipes and keeps working
             with no JavaScript at all. The script only adds the arrows, the
             rail and the caption swap on top of it. --}}
        <div class="tour rv"
             data-tour
             data-label-goto="{{ __('landing.tour_goto', ['title' => '%s']) }}"
             data-label-count="{{ __('landing.tour_of', ['current' => '%1$s', 'total' => '%2$s']) }}">

            <button class="tour-arrow tour-arrow--prev" type="button"
                    data-tour-prev aria-label="{{ __('landing.tour_prev') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                     stroke-linecap="round" aria-hidden="true"><path d="M14.5 5.5 8 12l6.5 6.5"/></svg>
            </button>

            <div class="tour-track" data-tour-track tabindex="0"
                 aria-label="{{ __('landing.app_eyebrow') }}">
                @foreach ($slides as $i => $slide)
                    <figure class="tour-slide @if ($i === 0) is-current @endif"
                            data-tour-slide
                            data-title="{{ __('landing.tour_'.$slide['key'].'_title') }}">
                        <div class="phone">
                            <img src="{{ asset('images/app/'.$slide['image']) }}"
                                 alt="{{ __('landing.tour_'.$slide['key'].'_alt') }}"
                                 width="576" height="1280" loading="lazy" decoding="async">
                        </div>
                    </figure>
                @endforeach
            </div>

            <button class="tour-arrow tour-arrow--next" type="button"
                    data-tour-next aria-label="{{ __('landing.tour_next') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                     stroke-linecap="round" aria-hidden="true"><path d="M9.5 5.5 16 12l-6.5 6.5"/></svg>
            </button>
        </div>

        {{-- Captions live outside the track: only the active one is shown, so a
             screen reader is not read six descriptions at once. --}}
        <div class="tour-captions rv" data-tour-captions aria-live="polite">
            @foreach ($slides as $i => $slide)
                <div class="tour-caption @if ($i === 0) is-current @endif" data-tour-caption>
                    <h3>{{ __('landing.tour_'.$slide['key'].'_title') }}</h3>
                    <p>{{ __('landing.tour_'.$slide['key'].'_text') }}</p>
                </div>
            @endforeach
        </div>

        {{-- Progress as a survey dimension line: a run of ticks between two end
             marks, the way a distance is broken up on a plat. Same motif as the
             accent under every heading on this page. --}}
        <div class="tour-rail rv" data-tour-rail role="tablist"
             aria-label="{{ __('landing.app_eyebrow') }}">
            @foreach ($slides as $i => $slide)
                <button class="tour-tick @if ($i === 0) is-current @endif" type="button"
                        role="tab" data-tour-tick data-index="{{ $i }}"
                        aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                        aria-label="{{ __('landing.tour_goto', [
                            'title' => __('landing.tour_'.$slide['key'].'_title'),
                        ]) }}">
                    <span></span>
                </button>
            @endforeach
        </div>

        <div class="tour-actions">
            <a class="btn btn--gold" href="#contact">{{ __('landing.app_cta_primary') }}</a>
            <a class="btn btn--wire-l" href="#contact">{{ __('landing.app_cta_secondary') }}</a>
        </div>

    </div>
</section>
