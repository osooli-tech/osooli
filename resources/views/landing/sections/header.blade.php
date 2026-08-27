<header class="hdr">
    <div class="inner">
        <a class="logo" href="{{ url('/') }}">
            <span class="logo-mk">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#001527" aria-hidden="true" focusable="false">
                    <path d="M12 2 3 8.4V22h6.2v-6.4h5.6V22H21V8.4z"/>
                </svg>
            </span>
            {{ __('nav.app_name') }}
        </a>

        <nav class="nav">
            <a href="#platform">{{ __('landing.nav_platform') }}</a>
            <a href="#app">{{ __('landing.nav_app') }}</a>
            <a href="#pricing">{{ __('landing.nav_pricing') }}</a>
            <a href="#trust">{{ __('landing.nav_trust') }}</a>
            <a href="#clients">{{ __('landing.nav_clients') }}</a>
        </nav>

        <div class="hdr-cta">
            <a class="lang" href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}"
               title="{{ app()->isLocale('ar') ? 'English' : 'العربية' }}">
                {{ app()->isLocale('ar') ? 'EN' : 'ع' }}
            </a>

            {{-- Someone already signed in gets the way back to their work, not
                 an invitation to sign in again. --}}
            @auth
                <a class="btn btn--sm btn--go" href="{{ route('dashboard') }}">
                    {{ __('landing.nav_dashboard') }}
                </a>
            @else
                <a class="btn btn--sm btn--wire-l" href="{{ route('login') }}">
                    {{ __('landing.nav_login') }}
                </a>
                <a class="btn btn--sm btn--go" href="#contact">
                    {{ __('landing.nav_request_demo') }}
                </a>
            @endauth
        </div>
    </div>
</header>
