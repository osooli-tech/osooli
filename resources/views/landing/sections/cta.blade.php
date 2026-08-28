<section class="band band--navy-grad pad" id="contact">
    <div class="inner fcta rv">
        <h2 class="h-section">
            {{ __('landing.cta_title') }}
            <x-landing.mark>{{ __('landing.cta_title_mark') }}</x-landing.mark>
        </h2>
        <p class="lede">{{ __('landing.cta_lede') }}</p>

        <button type="button" class="btn btn--gold btn--lg" data-open-demo-modal>
            {{ __('landing.cta_button') }}
            <x-landing.arrow />
        </button>

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

{{-- Demo request modal — plain markup + landing.js, matching this page's
     no-Livewire/no-Alpine bundle. Hidden by default via the [hidden] attribute
     so it degrades to nothing if the script fails to load. --}}
<div class="modal" data-demo-modal hidden>
    <div class="modal-backdrop" data-demo-close></div>

    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="demo-modal-title">
        <button type="button" class="modal-x" data-demo-close aria-label="{{ __('landing.request_form_close') }}">&times;</button>

        <h3 id="demo-modal-title" class="modal-title">{{ __('landing.request_form_title') }}</h3>
        <p class="modal-lede">{{ __('landing.request_form_lede') }}</p>

        <form data-demo-form novalidate
              action="{{ route('presentation-requests.store') }}"
              data-sending="{{ __('landing.request_form_sending') }}"
              data-success="{{ __('landing.request_form_success') }}"
              data-error="{{ __('landing.request_form_error') }}">
            <label class="modal-field">
                <span>{{ __('landing.request_form_name') }}</span>
                <input type="text" name="name" required maxlength="150" autocomplete="name">
            </label>

            <label class="modal-field">
                <span>{{ __('landing.request_form_phone') }}</span>
                <input type="tel" name="phone" required autocomplete="tel"
                       placeholder="{{ __('landing.request_form_phone_placeholder') }}">
            </label>

            <label class="modal-field">
                <span>{{ __('landing.request_form_message') }} <small>({{ __('landing.request_form_optional') }})</small></span>
                <textarea name="message" rows="3" maxlength="1000"></textarea>
            </label>

            <p class="modal-status" data-demo-status role="status" aria-live="polite"></p>

            <button type="submit" class="btn btn--gold btn--lg" data-demo-submit>
                {{ __('landing.request_form_submit') }}
            </button>
        </form>
    </div>
</div>
