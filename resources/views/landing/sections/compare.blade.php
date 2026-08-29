<section class="band band--paper pad">
    <div class="inner">
        <div class="row row--flip rv">
            <div class="row-copy">
                <span class="eyebrow">{{ __('landing.compare_eyebrow') }}</span>
                <h2 class="h-section">
                    {{ __('landing.compare_title') }}
                    <x-landing.mark>{{ __('landing.compare_title_mark') }}</x-landing.mark>
                </h2>
                <p class="lede">{{ __('landing.compare_lede') }}</p>
                <a class="btn btn--go" href="#app">
                    {{ __('landing.compare_cta') }}
                    <x-landing.arrow />
                </a>
            </div>
            <div class="row-art">@include('landing.art.compare')</div>
        </div>
    </div>
</section>
