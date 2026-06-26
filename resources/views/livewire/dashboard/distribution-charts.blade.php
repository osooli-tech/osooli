@php
    $isDark   = "document.documentElement.classList.contains('dark') ? 'dark' : 'light'";
    $font     = 'IBM Plex Sans Arabic, sans-serif';
    $cardCls  = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 shadow-sm border border-outline-variant dark:border-white/10';
    $titleCls = 'text-sm font-semibold text-on-surface dark:text-white mb-4';
    $emptyCls = 'flex items-center justify-center h-44 text-on-surface-variant dark:text-on-primary-container text-sm';
@endphp

<div class="space-y-4">

    {{-- Row 1: 3 small donuts ─────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

        {{-- 1 · Deed status --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_deed_status') }}</h3>
            @if (array_sum($byDeedStatus) === 0)
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'donut', height: 180, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                                 series: @json(array_values($byDeedStatus)),
                                 labels: @json(array_keys($byDeedStatus)),
                                 colors: ['#006c4e', '#b3261e'],
                                 theme: { mode: {{ $isDark }} },
                                 legend: { position: 'bottom', fontSize: '12px', fontFamily: '{{ $font }}' },
                                 dataLabels: { enabled: true, style: { fontSize: '11px' } },
                                 plotOptions: { pie: { donut: { size: '60%' } } },
                                 stroke: { width: 2 },
                             });
                             this.c.render();
                         }
                     }">
                    <div x-ref="el"></div>
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
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'donut', height: 180, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                                 series: @json(array_values($byAssetType)),
                                 labels: @json(array_keys($byAssetType)),
                                 colors: ['#002444','#006c4e','#c9a84c','#abc9f2','#68dbae'],
                                 theme: { mode: {{ $isDark }} },
                                 legend: { position: 'bottom', fontSize: '12px', fontFamily: '{{ $font }}' },
                                 dataLabels: { enabled: false },
                                 plotOptions: { pie: { donut: { size: '60%' } } },
                                 stroke: { width: 2 },
                             });
                             this.c.render();
                         }
                     }">
                    <div x-ref="el"></div>
                </div>
            @endif
        </div>

        {{-- 3 · Land transaction --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_land_transaction') }}</h3>
            @if (array_sum($byLandTransaction) === 0)
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'donut', height: 180, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                                 series: @json(array_values($byLandTransaction)),
                                 labels: @json(array_keys($byLandTransaction)),
                                 colors: ['#006c4e','#c9a84c','#002444','#abc9f2'],
                                 theme: { mode: {{ $isDark }} },
                                 legend: { position: 'bottom', fontSize: '12px', fontFamily: '{{ $font }}' },
                                 dataLabels: { enabled: false },
                                 plotOptions: { pie: { donut: { size: '60%' } } },
                                 stroke: { width: 2 },
                             });
                             this.c.render();
                         }
                     }">
                    <div x-ref="el"></div>
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
                <div wire:ignore
                     x-data="{
                         c: null,
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'bar', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                                 series: [{ name: '{{ __('dashboard.total_parcels') }}', data: @json(array_values($byCity)) }],
                                 xaxis: { categories: @json(array_keys($byCity)), labels: { style: { fontSize: '11px' } } },
                                 colors: ['#006c4e'],
                                 theme: { mode: {{ $isDark }} },
                                 dataLabels: { enabled: false },
                                 plotOptions: { bar: { borderRadius: 4 } },
                                 grid: { borderColor: 'rgba(0,0,0,0.05)' },
                             });
                             this.c.render();
                         }
                     }">
                    <div x-ref="el"></div>
                </div>
            @endif
        </div>

        {{-- 5 · By district --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_district') }}</h3>
            @if (empty($byDistrict))
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'bar', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                                 series: [{ name: '{{ __('dashboard.total_parcels') }}', data: @json(array_values($byDistrict)) }],
                                 xaxis: { categories: @json(array_keys($byDistrict)), labels: { style: { fontSize: '11px' } } },
                                 colors: ['#c9a84c'],
                                 theme: { mode: {{ $isDark }} },
                                 dataLabels: { enabled: false },
                                 plotOptions: { bar: { borderRadius: 4 } },
                                 grid: { borderColor: 'rgba(0,0,0,0.05)' },
                             });
                             this.c.render();
                         }
                     }">
                    <div x-ref="el"></div>
                </div>
            @endif
        </div>

    </div>{{-- /row-2 --}}

    {{-- Row 3: linked/not-linked + engineering offices ────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- 6 · Linked vs not-linked to survey decision --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_linked_decision') }}</h3>
            @if ($linkedToDecision === 0 && $notLinkedToDecision === 0)
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'donut', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                                 series: [{{ $linkedToDecision }}, {{ $notLinkedToDecision }}],
                                 labels: ['{{ __('dashboard.linked') }}', '{{ __('dashboard.not_linked') }}'],
                                 colors: ['#006c4e', '#b3261e'],
                                 theme: { mode: {{ $isDark }} },
                                 legend: { position: 'bottom', fontSize: '12px', fontFamily: '{{ $font }}' },
                                 dataLabels: { enabled: true, style: { fontSize: '12px' } },
                                 plotOptions: { pie: { donut: { size: '60%' } } },
                                 stroke: { width: 2 },
                             });
                             this.c.render();
                         }
                     }">
                    <div x-ref="el"></div>
                </div>
            @endif
        </div>

        {{-- 7 · By engineering office (horizontal bar) --}}
        <div class="{{ $cardCls }}">
            <h3 class="{{ $titleCls }}">{{ __('dashboard.distribution_by_office') }}</h3>
            @if (empty($byEngineeringOffice))
                <div class="{{ $emptyCls }}">{{ __('dashboard.no_data') }}</div>
            @else
                <div wire:ignore
                     x-data="{
                         c: null,
                         init() {
                             this.c = new ApexCharts(this.$refs.el, {
                                 chart: { type: 'bar', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: '{{ $font }}' },
                                 series: [{ name: '{{ __('dashboard.total_parcels') }}', data: @json(array_values($byEngineeringOffice)) }],
                                 xaxis: { categories: @json(array_keys($byEngineeringOffice)), labels: { style: { fontSize: '11px' } } },
                                 colors: ['#abc9f2'],
                                 theme: { mode: {{ $isDark }} },
                                 dataLabels: { enabled: false },
                                 plotOptions: { bar: { borderRadius: 4, horizontal: true } },
                                 grid: { borderColor: 'rgba(0,0,0,0.05)' },
                             });
                             this.c.render();
                         }
                     }">
                    <div x-ref="el"></div>
                </div>
            @endif
        </div>

    </div>{{-- /row-3 --}}

</div>{{-- /space-y-4 wrapper --}}
