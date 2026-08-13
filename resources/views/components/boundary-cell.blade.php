@props(['label', 'value' => null, 'dim' => null])

<div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
    <p class="text-on-surface-variant dark:text-on-primary-container mb-1">{{ $label }}</p>
    <p class="font-semibold text-on-surface dark:text-white leading-snug">{{ $value ?: '—' }}</p>
    @if ($dim)
        <p class="text-secondary data-tabular mt-0.5">{{ $dim }} م</p>
    @endif
</div>
