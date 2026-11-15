@php
    $isDark   = "document.documentElement.classList.contains('dark') ? 'dark' : 'light'";
    $font     = 'IBM Plex Sans Arabic, sans-serif';
    $cardCls  = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm border border-outline-variant dark:border-white/10';
    $titleCls = 'text-sm font-semibold text-on-surface dark:text-white mb-4';
    $emptyCls = 'flex items-center justify-center h-44 text-on-surface-variant dark:text-on-primary-container text-sm';
@endphp

<div class="space-y-4">

    {{-- Row 1: 2 small donuts ─────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- 1 · Deed status --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_deed_status') }}</h3>
            @if (array_sum($byDeedStatus) === 0)
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         labels: @js(array_keys($byDeedStatus)),
                         show(i) { const v = this.labels[i]; if (v !== undefined) window.dispatchEvent(new CustomEvent('map-filter', { detail: { type: 'deed_status', value: v } })); },
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'donut', height: 180, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}', events: { dataPointSelection: (e, c, cfg) => this.show(cfg.dataPointIndex), legendClick: (c, i) => this.show(i) } },
                                 series: @js(array_values($byDeedStatus)),
                                 labels: @js(array_keys($byDeedStatus)),
                                 colors: ['#006c4e', '#b3261e'],
                                 theme: { mode: {{ $isDark }} },
                                 legend: { position: 'bottom', onItemClick: { toggleDataSeries: false }, fontSize: '12px', fontFamily: '{{ $font }}' },
                                 dataLabels: { enabled: true, style: { fontSize: '11px' } },
                                 plotOptions: { pie: { donut: { size: '60%' } } },
                                 stroke: { width: 2 },
                             });
                             this.c.render();
                             window.addEventListener('sakuki:theme-changed', ({ detail }) => this.c.updateOptions({ theme: { mode: detail.dark ? 'dark' : 'light' } }));
                         }
                     }">
                    <div x-ref="el" class="cursor-pointer"></div>
                </div>
            @endif
        </div>

        {{-- 2 · Asset type --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_type') }}</h3>
            @if (array_sum($byAssetType) === 0)
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         labels: @js(array_keys($byAssetType)),
                         show(i) { const v = this.labels[i]; if (v !== undefined) window.dispatchEvent(new CustomEvent('map-filter', { detail: { type: 'asset_type', value: v } })); },
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'donut', height: 180, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}', events: { dataPointSelection: (e, c, cfg) => this.show(cfg.dataPointIndex), legendClick: (c, i) => this.show(i) } },
                                 series: @js(array_values($byAssetType)),
                                 labels: @js(array_keys($byAssetType)),
                                 colors: ['#002444','#006c4e','#c9a84c','#abc9f2','#68dbae'],
                                 theme: { mode: {{ $isDark }} },
                                 legend: { position: 'bottom', onItemClick: { toggleDataSeries: false }, fontSize: '12px', fontFamily: '{{ $font }}' },
                                 dataLabels: { enabled: false },
                                 plotOptions: { pie: { donut: { size: '60%' } } },
                                 stroke: { width: 2 },
                             });
                             this.c.render();
                             window.addEventListener('sakuki:theme-changed', ({ detail }) => this.c.updateOptions({ theme: { mode: detail.dark ? 'dark' : 'light' } }));
                         }
                     }">
                    <div x-ref="el" class="cursor-pointer"></div>
                </div>
            @endif
        </div>

    </div>{{-- /row-1 --}}

    {{-- Row 2: 2 bar charts ────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- 4 · By city --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_city') }}</h3>
            @if (empty($byCity))
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <x-rank-bars :items="$byCity" filter="city" bar="bg-secondary" />
            @endif
        </div>

        {{-- 5 · By district --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_district') }}</h3>
            @if (empty($byDistrict))
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <x-rank-bars :items="$byDistrict" filter="district" bar="bg-tertiary-container" />
            @endif
        </div>

    </div>{{-- /row-2 --}}

    {{-- Row 3: linked/not-linked + engineering offices ────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- 6 · By the authority that issued the survey decision --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_qrar_source') }}</h3>
            @if (array_sum($byQrarSource) === 0)
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         labels: @js(array_keys($byQrarSource)),
                         show(i) { const v = this.labels[i]; if (v !== undefined) window.dispatchEvent(new CustomEvent('map-filter', { detail: { type: 'qrar_source', value: v } })); },
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'donut', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}', events: { dataPointSelection: (e, c, cfg) => this.show(cfg.dataPointIndex), legendClick: (c, i) => this.show(i) } },
                                 series: @js(array_values($byQrarSource)),
                                 labels: @js(array_keys($byQrarSource)),
                                 colors: ['#006c4e', '#c9a84c', '#8a8f98'],
                                 theme: { mode: {{ $isDark }} },
                                 legend: { position: 'bottom', onItemClick: { toggleDataSeries: false }, fontSize: '12px', fontFamily: '{{ $font }}' },
                                 dataLabels: { enabled: true, style: { fontSize: '12px' } },
                                 plotOptions: { pie: { donut: { size: '60%' } } },
                                 stroke: { width: 2 },
                             });
                             this.c.render();
                             window.addEventListener('sakuki:theme-changed', ({ detail }) => this.c.updateOptions({ theme: { mode: detail.dark ? 'dark' : 'light' } }));
                         }
                     }">
                    <div x-ref="el" class="cursor-pointer"></div>
                </div>
            @endif
        </div>

        {{-- 7 · By engineering office --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_office') }}</h3>
            @if (empty($byEngineeringOffice))
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <x-rank-bars :items="$byEngineeringOffice" filter="office" bar="bg-primary" />
            @endif
        </div>

    </div>{{-- /row-3 --}}

</div>{{-- /space-y-4 wrapper --}}
