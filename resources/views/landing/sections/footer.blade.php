@php
    /**
     * A Saudi mobile number reads as +966 5X XXX XXXX; anything else is shown
     * exactly as configured. The tel: link always keeps the raw value.
     */
    $phone = (string) config('landing.contact_phone');
    $phoneDisplay = preg_replace('/^\+966(5\d)(\d{3})(\d{4})$/', '+966 $1 $2 $3', $phone);
    $email = (string) config('landing.contact_email');
@endphp

{{-- Three groups, each with one job: who we are and where to get the app,
     where to go, and how to reach us. Legal text sits under the dimension
     line, once. --}}
<footer class="band band--navy foot">
    <div class="inner">
        <div class="foot-main">

            <div class="foot-brand">
                <x-landing.logo />
                <p class="foot-about">{{ __('landing.foot_about') }}</p>

                <div class="foot-app">
                    <p>{{ __('landing.foot_app') }}</p>
                    <div class="foot-stores">
                        <x-landing.store name="App Store" :url="config('landing.app_store_url')" icon="apple" />
                        <x-landing.store name="Google Play" :url="config('landing.play_store_url')" icon="play" />
                    </div>
                </div>
            </div>

            {{-- Each link lands on its own section rather than all sharing #platform. --}}
            <nav class="foot-col" aria-labelledby="foot-platform-title">
                <h2 id="foot-platform-title">{{ __('landing.foot_platform') }}</h2>
                <ul class="foot-links">
                    <li><a href="{{ url('/') }}#parcels">{{ __('landing.parcels_eyebrow') }}</a></li>
                    <li><a href="{{ url('/') }}#deeds">{{ __('landing.deeds_eyebrow') }}</a></li>
                    <li><a href="{{ url('/') }}#decisions">{{ __('landing.decisions_eyebrow') }}</a></li>
                    <li><a href="{{ url('/') }}#trust">{{ __('landing.nav_trust') }}</a></li>
                    {{-- Restore alongside landing.sections.pricing. --}}
                    {{-- <li><a href="{{ url('/') }}#pricing">{{ __('landing.nav_pricing') }}</a></li> --}}
                    {{-- The header drops its sign-in button on small screens, so this
                         is the way in there. --}}
                    @auth
                        <li><a href="{{ route('dashboard') }}">{{ __('landing.nav_dashboard') }}</a></li>
                    @else
                        <li><a href="{{ route('login') }}">{{ __('landing.nav_login') }}</a></li>
                    @endauth
                </ul>
            </nav>

            <div class="foot-col">
                <h2>{{ __('landing.foot_contact') }}</h2>
                <address class="foot-contact">
                    <a href="tel:{{ $phone }}">
                        <small>{{ __('landing.foot_phone') }}</small>
                        {{-- dir="ltr" keeps the digits in order inside the RTL column. --}}
                        <b dir="ltr">{{ $phoneDisplay }}</b>
                    </a>
                    <a href="mailto:{{ $email }}">
                        <small>{{ __('landing.foot_email') }}</small>
                        <b dir="ltr">{{ $email }}</b>
                    </a>
                </address>
            </div>

        </div>

        <div class="foot-bot">
            <div>
                <p>{{ __('landing.foot_rights', ['year' => date('Y')]) }}</p>
                <p class="foot-supervision">{{ __('landing.foot_supervision') }}</p>
            </div>
            <nav class="foot-policies" aria-label="{{ __('landing.foot_legal') }}">
                <a href="{{ route('privacy.policy') }}">{{ __('landing.foot_privacy') }}</a>
                <a href="{{ route('terms.of.use') }}">{{ __('landing.foot_terms') }}</a>
            </nav>
        </div>
    </div>
</footer>
