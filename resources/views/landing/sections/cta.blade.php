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
            <x-landing.store name="App Store" :url="config('landing.app_store_url')" icon="apple" />
            <x-landing.store name="Google Play" :url="config('landing.play_store_url')" icon="play" />
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
                <span class="btn-label">{{ __('landing.request_form_submit') }}</span>
                <x-landing.btn-states />
            </button>
        </form>
    </div>
</div>
