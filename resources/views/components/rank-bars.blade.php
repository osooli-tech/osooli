@props(['items', 'filter', 'bar' => 'bg-secondary'])

{{-- Every entry, largest first; pressing one shows its parcels on the dashboard map. --}}
@php $max = max(1, ...array_values($items)); @endphp
<ul class="max-h-72 overflow-y-auto pe-1 space-y-1">
    @foreach ($items as $label => $count)
        <li>
            <button type="button"
                    x-on:click="window.dispatchEvent(new CustomEvent('map-filter', { detail: { type: @js($filter), value: @js((string) $label) } }))"
                    title="{{ __('dashboard.show_on_map') }}"
                    class="group w-full text-start rounded-lg px-2 py-1.5 hover:bg-surface-container dark:hover:bg-white/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary">
                <span class="flex items-center justify-between gap-3 text-xs">
                    <span class="truncate text-on-surface dark:text-white group-hover:text-secondary">{{ $label }}</span>
                    <span class="flex items-center gap-1 shrink-0 text-on-surface-variant dark:text-on-primary-container">
                        <span class="data-tabular font-semibold">{{ number_format($count) }}</span>
                        <span class="material-symbols-outlined text-sm opacity-0 group-hover:opacity-100">location_on</span>
                    </span>
                </span>
                <span class="mt-1 block h-1.5 rounded-full bg-surface-container-high dark:bg-white/10 overflow-hidden">
                    <span class="block h-full rounded-full {{ $bar }}" style="width: {{ round($count / $max * 100, 1) }}%"></span>
                </span>
            </button>
        </li>
    @endforeach
</ul>
