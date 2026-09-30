@props(['icon', 'label', 'value', 'decimals' => 0, 'suffix' => ''])

{{-- A frosted figure on the hero band; counts up from zero on load. --}}
<div class="rounded-2xl bg-white/10 ring-1 ring-white/10 backdrop-blur-sm px-4 py-3">
    <div class="flex items-center gap-1.5 text-xs text-primary-fixed-dim">
        <span class="material-symbols-outlined text-[16px] text-tertiary-fixed-dim">{{ $icon }}</span>{{ $label }}
    </div>
    <p class="mt-1 text-2xl font-bold data-tabular">
        @if (is_numeric($value))
            <span x-data="countUp({{ (float) $value }}, {{ (int) $decimals }})" x-text="display">{{ number_format((float) $value, (int) $decimals) }}</span>
        @else
            {{ $value }}
        @endif
        @if ($suffix !== '')
            <span class="text-sm font-medium text-tertiary-fixed-dim">{{ $suffix }}</span>
        @endif
    </p>
</div>
