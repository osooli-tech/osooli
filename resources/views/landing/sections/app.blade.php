<section class="band band--navy-grad pad" id="app">
    <div class="inner">
        <div class="row rv">
            <div class="row-copy">
                <span class="eyebrow">{{ __('landing.app_eyebrow') }}</span>
                <h2 class="h-section">
                    {{ __('landing.app_title') }}
                    <x-landing.mark>{{ __('landing.app_title_mark') }}</x-landing.mark>
                </h2>
                <p class="lede">{{ __('landing.app_lede') }}</p>
                <div class="hero-actions" style="margin-top:30px">
                    <a class="btn btn--gold" href="#contact">{{ __('landing.app_cta_primary') }}</a>
                    <a class="btn btn--wire-l" href="#contact">{{ __('landing.app_cta_secondary') }}</a>
                </div>
            </div>
            <div class="row-art">@include('landing.art.app')</div>
        </div>
    </div>
</section>
