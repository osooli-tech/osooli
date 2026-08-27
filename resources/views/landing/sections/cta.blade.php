<section class="band band--navy-grad pad" id="contact">
    <div class="inner fcta rv">
        <h2 class="h-section">
            {{ __('landing.cta_title') }}
            <x-landing.mark>{{ __('landing.cta_title_mark') }}</x-landing.mark>
        </h2>
        <p class="lede">{{ __('landing.cta_lede') }}</p>

        <a class="btn btn--gold btn--lg" href="mailto:{{ config('landing.contact_email') }}">
            {{ __('landing.cta_button') }}
            <x-landing.arrow />
        </a>

        <div class="stores">
            <a class="store" href="{{ config('landing.app_store_url') }}">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M17 12.5c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.5-.2-2.8.8-3.6.8s-1.9-.8-3.1-.8c-1.6 0-3 .9-3.8 2.4-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3.1-.7s1.8.7 3.1.7c1.3 0 2.1-1.1 2.9-2.3.9-1.3 1.3-2.6 1.3-2.7-.1 0-2.5-1-2.6-3.7M14.8 5.2c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.8 1.4-.6.7-1.2 1.8-1 2.9 1 .1 2.1-.5 2.8-1.3"/>
                </svg>
                <u><small>{{ __('landing.store_from') }}</small><b>App Store</b></u>
            </a>

            <a class="store" href="{{ config('landing.play_store_url') }}">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                    <path d="M3.6 1.8 14 12 3.6 22.2c-.4-.3-.6-.8-.6-1.4V3.2c0-.6.2-1.1.6-1.4m11.9 7.4 2.8 2.8-2.8 2.8L12.7 12zM4.9 1.2l10.1 5.8-2.5 2.5zm0 21.6 7.6-8.3 2.5 2.5z"/>
                </svg>
                <u><small>{{ __('landing.store_from') }}</small><b>Google Play</b></u>
            </a>
        </div>
    </div>
</section>
