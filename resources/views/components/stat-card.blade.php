<div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-lg p-2.5 border-s-2 {{ $borderColor }} shadow-sm">
    <div class="flex items-start justify-between gap-2">
        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-medium text-on-surface-variant dark:text-on-primary-container mb-0.5 leading-tight">
                {{ $label }}
            </p>
            <p class="text-lg font-bold text-on-surface dark:text-white leading-none data-tabular">
                {{ $value }}
            </p>
            @if ($subtext)
                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-1 leading-tight">{{ $subtext }}</p>
            @endif
        </div>
        <div class="w-7 h-7 rounded-md {{ $iconBg }} flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[16px] {{ $iconColor }}"
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
