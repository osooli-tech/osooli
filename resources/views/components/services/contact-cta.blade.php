{{-- Only channels the client has actually supplied render — a placeholder
     phone number would read as real and could mislead someone into calling
     it. --}}
<div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 sm:p-6
            border border-outline-variant dark:border-white/10 shadow-sm mt-6">
    <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">
        {{ __('services.contact_heading') }}
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @if (config('landing.contact_whatsapp'))
            <a href="https://wa.me/{{ preg_replace('/\D/', '', config('landing.contact_whatsapp')) }}"
               target="_blank" rel="noopener"
               class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant dark:border-white/10
                      hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-[20px] text-secondary shrink-0">chat</span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('services.contact_whatsapp') }}</p>
                    <p class="text-sm font-medium text-on-surface dark:text-white data-tabular ltr" dir="ltr">{{ config('landing.contact_whatsapp') }}</p>
                </div>
            </a>
        @endif

        @if (config('landing.contact_phone'))
            <a href="tel:{{ config('landing.contact_phone') }}"
               class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant dark:border-white/10
                      hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-[20px] text-secondary shrink-0">call</span>
                <div class="min-w-0">
                    <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('services.contact_phone') }}</p>
                    <p class="text-sm font-medium text-on-surface dark:text-white data-tabular ltr" dir="ltr">{{ config('landing.contact_phone') }}</p>
                </div>
            </a>
        @endif

        <a href="mailto:{{ config('landing.contact_email') }}"
           class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant dark:border-white/10
                  hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
            <span class="material-symbols-outlined text-[20px] text-secondary shrink-0">mail</span>
            <div class="min-w-0">
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('services.contact_email') }}</p>
                <p class="text-sm font-medium text-on-surface dark:text-white truncate ltr" dir="ltr">{{ config('landing.contact_email') }}</p>
            </div>
        </a>

        <div class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant dark:border-white/10">
            <span class="material-symbols-outlined text-[20px] text-secondary shrink-0">schedule</span>
            <div class="min-w-0">
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('services.contact_hours') }}</p>
                <p class="text-sm font-medium text-on-surface dark:text-white">{{ __('services.contact_hours_value') }}</p>
            </div>
        </div>
    </div>
</div>
