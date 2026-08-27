<footer class="band band--navy foot">
    <div class="inner">
        <div class="foot-grid">
            <div>
                <x-landing.logo />
                <p class="foot-about">{{ __('landing.foot_about') }}</p>
            </div>

            <div>
                <h2>{{ __('landing.foot_platform') }}</h2>
                <a href="#platform">{{ __('landing.parcels_eyebrow') }}</a>
                <a href="#platform">{{ __('landing.deeds_eyebrow') }}</a>
                <a href="#platform">{{ __('landing.decisions_eyebrow') }}</a>
                <a href="#pricing">{{ __('landing.nav_pricing') }}</a>
            </div>

            <div>
                <h2>{{ __('landing.foot_app') }}</h2>
                <a href="#app">{{ __('landing.app_eyebrow') }}</a>
                <a href="{{ config('landing.app_store_url') }}">App Store</a>
                <a href="{{ config('landing.play_store_url') }}">Google Play</a>
                <a href="#trust">{{ __('landing.nav_trust') }}</a>
            </div>

            <div>
                <h2>{{ __('landing.foot_company') }}</h2>
                <a href="mailto:{{ config('landing.contact_email') }}">{{ __('landing.foot_contact') }}</a>
                <a href="{{ route('privacy.policy') }}">{{ __('landing.foot_privacy') }}</a>
                <a href="{{ route('terms.of.use') }}">{{ __('landing.foot_terms') }}</a>
                <a href="{{ route('login') }}">{{ __('landing.nav_login') }}</a>
            </div>
        </div>

        <div class="foot-bot">
            <span>{{ __('landing.foot_rights', ['year' => date('Y')]) }}</span>
            <nav>
                <a href="{{ route('privacy.policy') }}">{{ __('landing.foot_privacy_short') }}</a>
                <a href="{{ route('terms.of.use') }}">{{ __('landing.foot_terms_short') }}</a>
            </nav>
        </div>
    </div>
</footer>
