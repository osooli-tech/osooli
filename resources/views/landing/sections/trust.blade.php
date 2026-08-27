<section class="band band--paper pad" id="trust">
    <div class="inner">
        <div class="row row--flip rv">
            <div class="row-copy">
                <span class="eyebrow">{{ __('landing.trust_eyebrow') }}</span>
                <h2 class="h-section">
                    {{ __('landing.trust_title') }}
                    <x-landing.mark>{{ __('landing.trust_title_mark') }}</x-landing.mark>
                </h2>
                <p class="lede">{{ __('landing.trust_lede') }}</p>
                <ul class="checks">
                    <x-landing.check>{{ __('landing.trust_check_1') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.trust_check_2') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.trust_check_3') }}</x-landing.check>
                </ul>
                <a class="btn btn--go" href="{{ route('privacy.policy') }}">
                    {{ __('landing.trust_cta') }}
                    <x-landing.arrow />
                </a>
            </div>
            <div class="row-art">@include('landing.art.trust')</div>
        </div>
    </div>
</section>
