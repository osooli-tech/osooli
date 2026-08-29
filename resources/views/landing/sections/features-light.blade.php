{{-- Two feature rows on one white band: parcels, then deeds and owners.
     They alternate sides, which is the page's rhythm throughout. --}}
<section class="band band--paper pad" id="platform">
    <div class="inner">

        <div class="row rv">
            <div class="row-copy">
                <span class="eyebrow">{{ __('landing.parcels_eyebrow') }}</span>
                <h2 class="h-section">
                    {{ __('landing.parcels_title') }}
                    <x-landing.mark>{{ __('landing.parcels_title_mark') }}</x-landing.mark>
                </h2>
                <p class="lede">{{ __('landing.parcels_lede') }}</p>
                <ul class="checks">
                    <x-landing.check>{{ __('landing.parcels_check_1') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.parcels_check_2') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.parcels_check_3') }}</x-landing.check>
                </ul>
                <a class="btn btn--go" href="#contact">
                    {{ __('landing.parcels_cta') }}
                    <x-landing.arrow />
                </a>
            </div>
            <div class="row-art">@include('landing.art.parcels')</div>
        </div>

        <div class="row row--flip rv">
            <div class="row-copy">
                <span class="eyebrow">{{ __('landing.deeds_eyebrow') }}</span>
                <h2 class="h-section">
                    {{ __('landing.deeds_title') }}
                    <x-landing.mark>{{ __('landing.deeds_title_mark') }}</x-landing.mark>
                </h2>
                <p class="lede">{{ __('landing.deeds_lede') }}</p>
                <ul class="checks">
                    <x-landing.check>{{ __('landing.deeds_check_1') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.deeds_check_2') }}</x-landing.check>
                    <x-landing.check>{{ __('landing.deeds_check_3') }}</x-landing.check>
                </ul>
                <a class="btn btn--go" href="#contact">
                    {{ __('landing.deeds_cta') }}
                    <x-landing.arrow />
                </a>
            </div>
            <div class="row-art">@include('landing.art.deeds')</div>
        </div>

    </div>
</section>
