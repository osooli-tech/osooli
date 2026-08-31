@props(['icon', 'title', 'description', 'serviceName'])

<div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 flex flex-col h-full
            border border-outline-variant dark:border-white/10 shadow-sm">
    <div class="w-11 h-11 rounded-xl bg-secondary/10 dark:bg-secondary/20 flex items-center justify-center mb-3.5">
        <span class="material-symbols-outlined text-[22px] text-secondary"
              style="font-variation-settings: 'FILL' 1;">{{ $icon }}</span>
    </div>
    <h3 class="font-semibold text-on-surface dark:text-white text-sm mb-1.5">{{ $title }}</h3>
    <p class="text-xs text-on-surface-variant dark:text-on-primary-container leading-relaxed flex-1">
        {{ $description }}
    </p>
    <a href="https://wa.me/{{ preg_replace('/\D/', '', config('landing.contact_whatsapp')) }}?text={{ rawurlencode(__('services.request_subject', ['service' => $serviceName])) }}"
       target="_blank" rel="noopener"
       class="mt-4 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium
              bg-secondary text-white hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined text-[15px]">chat</span>
        {{ __('services.request_service') }}
    </a>
</div>
