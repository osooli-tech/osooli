<div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4 border-s-4 {{ $borderColor }} shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1.5 leading-tight">
                {{ $label }}
            </p>
            <p class="text-2xl font-bold text-on-surface dark:text-white leading-none data-tabular">
                {{ $value }}
            </p>
            @if ($subtext)
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-1.5 leading-tight">{{ $subtext }}</p>
            @endif
        </div>
        <div class="w-10 h-10 rounded-xl {{ $iconBg }} flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[20px] {{ $iconColor }}"
                  style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                {{ $icon }}
            </span>
        </div>
    </div>

    @if (! is_null($trend))
        <div class="mt-3 flex items-center gap-1.5 text-xs">
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
