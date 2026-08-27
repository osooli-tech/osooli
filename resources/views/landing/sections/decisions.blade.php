<section class="band band--navy pad">
    <div class="inner">
        <div class="row rv">
            <div class="row-copy">
                <span class="eyebrow">{{ __('landing.decisions_eyebrow') }}</span>
                <h2 class="h-section">
                    {{ __('landing.decisions_title') }}
                    <x-landing.mark>{{ __('landing.decisions_title_mark') }}</x-landing.mark>
                </h2>
                <p class="lede">{{ __('landing.decisions_lede') }}</p>
                <ul class="checks">
                    <x-landing.check>{{ __('landing.decisions_check_1') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.decisions_check_2') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.decisions_check_3') }}</x-landing.check>
                </ul>
                <a class="btn btn--gold" href="#contact">
                    {{ __('landing.decisions_cta') }}
                    <x-landing.arrow />
                </a>
            </div>
            <div class="row-art">@include('landing.art.decisions')</div>
        </div>
    </div>
</section>
