<div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-xl p-3 border-s-[3px] {{ $borderColor }} shadow-sm">
    <div class="flex items-start justify-between gap-2">
        <div class="flex-1 min-w-0">
            <p class="text-[11px] font-medium text-on-surface-variant dark:text-on-primary-container mb-1 leading-tight">
                {{ $label }}
            </p>
            <p class="text-xl font-bold text-on-surface dark:text-white leading-none data-tabular">
                {{ $value }}
            </p>
            @if ($subtext)
                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-1 leading-tight">{{ $subtext }}</p>
            @endif
        </div>
        <div class="w-8 h-8 rounded-lg {{ $iconBg }} flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px] {{ $iconColor }}"
                  style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                {{ $icon }}
            </span>
        </div>
    </div>

    @if (! is_null($trend))
        <div class="mt-2 flex items-center gap-1.5 text-xs">
            <span class="material-symbols-outlined text-[14px] {{ $trend >= 0 ? 'text-secondary' : 'text-error' }}"
                  style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                {{ $trend >= 0 ? 'trending_up' : 'trending_down' }}
            </span>
            <span class="{{ $trend >= 0 ? 'text-secondary' : 'text-error' }} font-semibold">
                {{ number_format(abs($trend), 1) }}%
            </span>
            @if ($trendLabel)
                <span class="text-on-surface-variant dark:text-on-primary-container">{{ $trendLabel }}</span>
            @endif
        </div>
    @endif
</div>
