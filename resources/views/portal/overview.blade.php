@extends('portal.layout')

@section('title', $account->isCompany() && $account->name ? $account->name : __('portal.overview_title'))

@section('content')
@php
    $labels = [
        'sqm' => __('dashboard.area_unit_sqm'),
        'sar' => __('parcels.currency'),
        'open' => __('portal.explorer_open'),
        'streets' => __('parcels.show_streets'),
        'satellite' => __('parcels.show_satellite'),
    ];
    $chip = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-all';
    $field = 'px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10 text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40';
@endphp
<div class="space-y-5"
     x-data="accountExplorer(@js($explorer), @js($labels), @js(route('portal.parcels.show', '__ID__')), @js($showValue))">

    {{-- ── Who this is, and the figures for whatever is selected below ── --}}
    <x-portal.page-hero :icon="$account->isCompany() ? 'domain' : 'dashboard'"
                        :title="$account->isCompany() && $account->name ? $account->name : __('portal.overview_title')"
                        :subtitle="$account->isCompany() ? __('portal.overview_company_subtitle') : __('portal.overview_subtitle')" id="overview">
        @if ($account->isCompany() && $account->cr_number)
            <p class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1 rounded-full bg-white/10 ring-1 ring-white/20">
                <span class="material-symbols-outlined text-[15px]">badge</span>{{ __('portal.cr_number') }}
                <span class="data-tabular" dir="ltr">{{ $account->cr_number }}</span>
            </p>
        @endif
        <div class="grid grid-cols-2 {{ $showValue ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-3 mt-6">
            @foreach ([
                ['icon' => 'group', 'label' => __('portal.overview_owners'), 'value' => 'kpi.owners'],
                ['icon' => 'map', 'label' => __('dashboard.total_parcels'), 'value' => 'kpi.parcels'],
                ['icon' => 'square_foot', 'label' => __('dashboard.total_area'), 'value' => 'kpi.area', 'suffix' => __('dashboard.area_unit_sqm')],
                ['icon' => 'location_city', 'label' => __('portal.explorer_districts'), 'value' => 'kpi.districts'],
                ...($showValue ? [['icon' => 'payments', 'label' => __('portal.overview_value'), 'value' => 'kpi.value', 'suffix' => __('parcels.currency')]] : []),
            ] as $tile)
                <div class="rounded-2xl p-4 bg-white/10 ring-1 ring-white/15 backdrop-blur-sm">
                    <p class="flex items-center gap-1.5 text-[11px] text-primary-fixed-dim">
                        <span class="material-symbols-outlined text-[16px] text-tertiary-fixed-dim">{{ $tile['icon'] }}</span>{{ $tile['label'] }}
                    </p>
                    <p class="mt-1 text-2xl font-bold data-tabular">
                        <span x-text="fmt({{ $tile['value'] }})"></span>
                        @isset($tile['suffix'])<span class="text-xs font-normal opacity-80">{{ $tile['suffix'] }}</span>@endisset
                    </p>
                </div>
            @endforeach
        </div>
        <p class="mt-3 flex items-center gap-1 text-xs opacity-80" x-show="filtering" x-cloak>
            <span class="material-symbols-outlined text-[14px]">filter_alt</span>{{ __('portal.explorer_figures_for_selection') }}
        </p>
    </x-portal.page-hero>

    {{-- ── Owners: one card each; a click shows only their land ── --}}
    <section class="rounded-3xl p-4 bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10 shadow-sm">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="flex items-center gap-2 font-semibold">
                <span class="material-symbols-outlined text-[20px] text-secondary">diversity_3</span>{{ __('portal.explorer_owners') }}
                <span class="text-xs font-normal text-on-surface-variant dark:text-on-primary-container">{{ __('portal.explorer_owners_hint') }}</span>
            </h2>
            <button type="button" @click="selected = []" x-show="selected.length" x-cloak class="text-xs font-medium text-secondary hover:underline">{{ __('portal.explorer_all') }}</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
            <template x-for="o in data.owners" :key="o.id">
                <button type="button" @click="toggleOwner(o.id)"
                        class="group relative text-start rounded-2xl p-3 border transition-all hover:-translate-y-0.5 hover:shadow-md"
                        :class="isSelected(o.id) ? 'border-transparent shadow-lg' : (selected.length ? 'border-outline-variant/50 dark:border-white/5 opacity-55' : 'border-outline-variant dark:border-white/10')"
                        :style="isSelected(o.id) ? `box-shadow: 0 0 0 2px ${o.color}, 0 10px 24px -12px ${o.color}` : ''">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 shrink-0 rounded-2xl text-white flex items-center justify-center font-bold text-lg"
                              :style="`background: linear-gradient(135deg, ${o.color}, ${o.color}bb)`" x-text="o.name.trim().charAt(0)"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate" x-text="o.name"></p>
                            <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container data-tabular">
                                <span x-text="o.parcels"></span> {{ __('owners.portfolio_parcels_unit') }} ·
                                <span x-text="fmt(o.area)"></span> {{ __('dashboard.area_unit_sqm') }}
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-[20px] transition-opacity" :style="`color: ${o.color}`"
                              :class="isSelected(o.id) ? 'opacity-100' : 'opacity-0 group-hover:opacity-60'">check_circle</span>
                    </div>
                    {{-- Their part of the account's land --}}
                    <div class="mt-3 h-1.5 rounded-full bg-surface-container dark:bg-white/5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" :style="`width: ${pctOfTotal(o.area)}%; background: ${o.color}`"></div>
                    </div>
                    <p class="mt-1 text-[10px] text-on-surface-variant dark:text-on-primary-container data-tabular">
                        <span x-text="pctOfTotal(o.area)"></span>% {{ __('portal.explorer_area_share') }}
                        <template x-if="showValue && o.value !== null">
                            <span> · <span class="text-secondary font-semibold" x-text="fmt(o.value)"></span> {{ __('parcels.currency') }}</span>
                        </template>
                    </p>
                </button>
            </template>
        </div>
    </section>

    {{-- ── Map and filters ── --}}
    <section class="rounded-3xl overflow-hidden bg-surface-container-lowest dark:bg-[#141b29] border border-outline-variant/70 dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap items-center gap-2 p-3 border-b border-outline-variant/60 dark:border-white/10">
            <div class="relative flex-1 min-w-[200px]">
                <span class="material-symbols-outlined absolute inset-y-0 end-3 flex items-center text-[18px] text-on-surface-variant dark:text-on-primary-container pointer-events-none">search</span>
                <input type="search" x-model.debounce.200ms="search" placeholder="{{ __('portal.explorer_search') }}" class="{{ $field }} w-full pe-10">
            </div>
            <select x-model="district" class="{{ $field }}">
                <option value="">{{ __('portal.explorer_district') }}</option>
                <template x-for="d in districts" :key="d"><option :value="d" x-text="d"></option></template>
            </select>
            <select x-model="assetType" class="{{ $field }}" x-show="assetTypes.length > 1">
                <option value="">{{ __('portal.explorer_type') }}</option>
                <template x-for="t in assetTypes" :key="t"><option :value="t" x-text="t"></option></template>
            </select>
            <select x-model="sort" class="{{ $field }}">
                <option value="area">{{ __('portal.explorer_sort_area') }}</option>
                @if ($showValue)<option value="value">{{ __('portal.explorer_sort_value') }}</option>@endif
                <option value="no">{{ __('portal.explorer_sort_no') }}</option>
            </select>
            <button type="button" @click="resetFilters()" x-show="filtering" x-cloak class="{{ $chip }} bg-error/10 text-error">
                <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>{{ __('portal.explorer_reset') }}
            </button>
        </div>

        <div class="relative">
            <div x-ref="map" class="w-full h-[460px] lg:h-[540px] bg-surface-container dark:bg-white/5"></div>
            {{-- What is on the map right now --}}
            <div class="absolute top-3 end-3 z-10 flex flex-col gap-2 items-end pointer-events-none">
                <span class="pointer-events-auto {{ $chip }} bg-primary/90 text-white shadow-lg backdrop-blur">
                    <span class="material-symbols-outlined text-[16px]">layers</span>
                    <span class="data-tabular"><span x-text="filtered.length"></span>/<span x-text="data.parcels.length"></span></span> {{ __('owners.portfolio_parcels_unit') }}
                </span>
                <button type="button" @click="fitAll()" class="pointer-events-auto {{ $chip }} bg-white/95 dark:bg-[#1a1f2e]/95 text-on-surface dark:text-white shadow-lg">
                    <span class="material-symbols-outlined text-[16px]">fit_screen</span>{{ __('portal.explorer_fit') }}
                </button>
            </div>
            <div x-show="filtered.length === 0" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-surface/70 dark:bg-[#141b29]/70 backdrop-blur-sm">
                <p class="flex items-center gap-2 text-sm font-medium"><span class="material-symbols-outlined">travel_explore</span>{{ __('portal.explorer_none') }}</p>
            </div>
        </div>
    </section>

    {{-- ── The parcels, as cards or a table ── --}}
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
                <span class="font-bold text-on-surface dark:text-white data-tabular" x-text="filtered.length"></span> {{ __('portal.explorer_of') }} <span class="data-tabular" x-text="data.parcels.length"></span> {{ __('owners.portfolio_parcels_unit') }}
            </p>
            <div class="flex items-center gap-1 p-1 rounded-xl bg-surface-container dark:bg-white/5">
                <button type="button" @click="view = 'cards'" class="{{ $chip }}" :class="view === 'cards' ? 'bg-secondary text-white' : ''"><span class="material-symbols-outlined text-[16px]">grid_view</span>{{ __('portal.view_cards') }}</button>
                <button type="button" @click="view = 'table'" class="{{ $chip }}" :class="view === 'table' ? 'bg-secondary text-white' : ''"><span class="material-symbols-outlined text-[16px]">table_rows</span>{{ __('portal.view_table') }}</button>
            </div>
        </div>

        <div x-show="view === 'cards'" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="p in filtered" :key="p.id">
                <article class="flex flex-col h-full rounded-2xl overflow-hidden bg-surface-container-lowest dark:bg-[#1a1f2e] border border-outline-variant dark:border-white/10 shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all"
                         :class="focused === p.id ? 'ring-2 ring-tertiary-container' : ''">
                    {{-- Each holder's part, in their colour --}}
                    <div class="h-1.5 flex">
                        <template x-for="o in p.owners" :key="o.id"><span class="h-full" :style="`width: ${o.share / shareSum(p) * 100}%; background: ${owner(o.id).color}`"></span></template>
                    </div>
                    <div class="flex gap-3 p-4">
                        <svg viewBox="0 0 100 100" class="w-20 h-20 shrink-0 rounded-xl bg-surface-container-low dark:bg-white/5" aria-hidden="true">
                            <path :d="shape(p)" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke"
                                  :style="`fill: ${owner(p.owners[0].id).color}33; stroke: ${owner(p.owners[0].id).color}`" />
                        </svg>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-lg font-bold data-tabular truncate" x-text="p.parcel_no || '—'"></p>
                                <span x-show="p.owners.length > 1" class="shrink-0 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-tertiary-container/40 text-on-tertiary-container dark:text-tertiary-fixed-dim">{{ __('portal.explorer_shared') }}</span>
                            </div>
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container truncate" x-text="[p.district, p.city].filter(Boolean).join(' · ') || '—'"></p>
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container truncate" x-show="p.plan_no">{{ __('parcels.plan_no') }} <span class="data-tabular" x-text="p.plan_no"></span></p>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 px-4 text-sm">
                        <div class="rounded-xl bg-surface-container-low dark:bg-white/5 p-2">
                            <dt class="text-[10px] text-on-surface-variant dark:text-on-primary-container">{{ __('dashboard.total_area') }}</dt>
                            <dd class="font-bold data-tabular"><span x-text="fmt(p.area)"></span> <span class="text-[10px] font-normal">{{ __('dashboard.area_unit_sqm') }}</span></dd>
                        </div>
                        @if ($showValue)
                            <div class="rounded-xl bg-surface-container-low dark:bg-white/5 p-2">
                                <dt class="text-[10px] text-on-surface-variant dark:text-on-primary-container">{{ __('portal.overview_value') }}</dt>
                                <dd class="font-bold data-tabular text-secondary" x-text="p.value === null ? '—' : fmt(p.value)"></dd>
                            </div>
                        @else
                            <div class="rounded-xl bg-surface-container-low dark:bg-white/5 p-2">
                                <dt class="text-[10px] text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.asset_type') }}</dt>
                                <dd class="font-bold" x-text="p.asset_type || '—'"></dd>
                            </div>
                        @endif
                    </dl>
                    {{-- Who holds it, and how much each --}}
                    <ul class="flex flex-wrap gap-1.5 px-4 pt-3">
                        <template x-for="o in p.owners" :key="o.id">
                            <li class="inline-flex items-center gap-1 text-[11px] ps-1 pe-2 py-0.5 rounded-full bg-surface-container dark:bg-white/5">
                                <span class="w-4 h-4 rounded-full text-[9px] text-white flex items-center justify-center font-bold" :style="`background: ${owner(o.id).color}`" x-text="owner(o.id).name.trim().charAt(0)"></span>
                                <span class="truncate max-w-[120px]" x-text="owner(o.id).name"></span>
                                <span class="data-tabular opacity-70" x-show="o.share < 0.9999" x-text="pct(o.share)"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="mt-auto flex items-center gap-2 p-4">
                        <button type="button" @click="focus(p.id)" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl text-xs font-medium bg-surface-container dark:bg-white/5 hover:bg-surface-container-high dark:hover:bg-white/10">
                            <span class="material-symbols-outlined text-[16px]">my_location</span>{{ __('portal.explorer_on_map') }}
                        </button>
                        <a :href="url(p.id)" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-2 rounded-xl text-xs font-medium bg-primary text-white hover:opacity-90">
                            {{ __('portal.explorer_open') }}<span class="material-symbols-outlined text-[16px] rtl:rotate-180">arrow_forward</span>
                        </a>
                    </div>
                </article>
            </template>
        </div>

        <div x-show="view === 'table'" x-cloak class="overflow-x-auto rounded-2xl bg-surface-container-lowest dark:bg-[#1a1f2e] border border-outline-variant dark:border-white/10 shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10 text-[11px] font-bold text-on-surface-variant dark:text-on-primary-container">
                        <th class="px-4 py-3 text-start">{{ __('parcels.parcel_no') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('portal.explorer_owners') }}</th>
                        <th class="px-4 py-3 text-start hidden md:table-cell">{{ __('portal.explorer_district') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('dashboard.total_area') }}</th>
                        @if ($showValue)<th class="px-4 py-3 text-start hidden md:table-cell">{{ __('portal.overview_value') }}</th>@endif
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/40 dark:divide-white/5">
                    <template x-for="p in filtered" :key="p.id">
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 cursor-pointer" @click="focus(p.id)">
                            <td class="px-4 py-3 font-semibold data-tabular" x-text="p.parcel_no || '—'"></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-x-3 gap-y-1">
                                    <template x-for="o in p.owners" :key="o.id">
                                        <span class="inline-flex items-center gap-1 text-[11px]"><span class="w-2.5 h-2.5 rounded-full" :style="`background: ${owner(o.id).color}`"></span><span x-text="owner(o.id).name"></span></span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-4 py-3 hidden md:table-cell" x-text="p.district || '—'"></td>
                            <td class="px-4 py-3 data-tabular" x-text="fmt(p.area)"></td>
                            @if ($showValue)<td class="px-4 py-3 data-tabular hidden md:table-cell text-secondary" x-text="p.value === null ? '—' : fmt(p.value)"></td>@endif
                            <td class="px-4 py-3 text-end"><a :href="url(p.id)" @click.stop class="text-xs font-medium text-secondary hover:underline">{{ __('portal.explorer_open') }}</a></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
/**
 * The overview explorer: owner cards and filters narrow one list of parcels,
 * and the map, the figures and the cards all follow it.
 */
function accountExplorer(data, labels, urlTemplate, showValue) {
    const owners = Object.fromEntries(data.owners.map((o) => [o.id, o]));
    const ringsOf = (g) => (! g ? [] : g.type === 'MultiPolygon' ? g.coordinates.map((poly) => poly[0]) : [g.coordinates[0]]);
    const pointsOf = (g) => ringsOf(g).flat();
    const centre = (g) => {
        const pts = pointsOf(g);
        if (! pts.length) return null;
        const xs = pts.map((p) => p[0]);
        const ys = pts.map((p) => p[1]);
        return [(Math.min(...xs) + Math.max(...xs)) / 2, (Math.min(...ys) + Math.max(...ys)) / 2];
    };
    const totalArea = data.owners.reduce((s, o) => s + o.area, 0) || 1;

    return {
        data, showValue,
        selected: [], search: '', district: '', assetType: '', sort: 'area', view: 'cards', focused: null,
        map: null, mapboxgl: null, currentPopup: null,

        get districts() { return [...new Set(data.parcels.map((p) => p.district).filter(Boolean))].sort(); },
        get assetTypes() { return [...new Set(data.parcels.map((p) => p.asset_type).filter(Boolean))].sort(); },
        get filtering() { return this.search !== '' || this.district !== '' || this.assetType !== '' || this.selected.length > 0; },

        get filtered() {
            const q = this.search.trim().toLowerCase();
            const list = data.parcels.filter((p) =>
                (this.selected.length === 0 || p.owners.some((o) => this.selected.includes(o.id)))
                && (q === '' || String(p.parcel_no ?? '').toLowerCase().includes(q) || String(p.plan_no ?? '').toLowerCase().includes(q))
                && (this.district === '' || p.district === this.district)
                && (this.assetType === '' || p.asset_type === this.assetType));
            const by = {
                area: (a, b) => this.areaOf(b) - this.areaOf(a),
                value: (a, b) => (b.value ?? -1) - (a.value ?? -1),
                no: (a, b) => String(a.parcel_no ?? '').localeCompare(String(b.parcel_no ?? ''), 'ar', { numeric: true }),
            };
            return list.sort(by[this.sort]);
        },

        shareSum(p) { return p.owners.reduce((s, o) => s + o.share, 0) || 1; },

        /** A parcel's area at the selected owners' part — the whole held area when nobody is selected. */
        areaOf(p) {
            if (this.selected.length === 0) return p.area;
            const mine = p.owners.filter((o) => this.selected.includes(o.id)).reduce((s, o) => s + o.share, 0);
            return p.area * (mine / this.shareSum(p));
        },

        get kpi() {
            const list = this.filtered;
            const priced = list.filter((p) => p.value !== null);
            return {
                owners: this.selected.length || data.owners.length,
                parcels: list.length,
                area: list.reduce((s, p) => s + this.areaOf(p), 0),
                districts: new Set(list.map((p) => p.district).filter(Boolean)).size,
                value: priced.length ? priced.reduce((s, p) => s + p.value * (this.areaOf(p) / (p.area || 1)), 0) : null,
            };
        },

        owner(id) { return owners[id] ?? { name: '—', color: '#94a3b8' }; },
        isSelected(id) { return this.selected.includes(id); },
        toggleOwner(id) { this.selected = this.isSelected(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id]; },
        resetFilters() { this.selected = []; this.search = ''; this.district = ''; this.assetType = ''; },
        url(id) { return urlTemplate.replace('__ID__', id); },
        pctOfTotal(area) { return Math.round((area / totalArea) * 1000) / 10; },
        pct(share) { return (Math.round(share * 1000) / 10) + '%'; },
        fmt(n) { return n === null || n === undefined ? '—' : Math.round(n).toLocaleString('en-US'); },

        /** The parcel's outline in a 100x100 box, longitude scaled by cos(latitude) to keep its proportions. */
        shape(p) {
            const rings = ringsOf(p.geometry);
            const pts = rings.flat();
            if (! pts.length) return 'M20 20 H80 V80 H20 Z';
            const xs = pts.map((q) => q[0]);
            const ys = pts.map((q) => q[1]);
            const minX = Math.min(...xs); const maxY = Math.max(...ys);
            const k = Math.cos(((Math.min(...ys) + maxY) / 2) * Math.PI / 180);
            const w = (Math.max(...xs) - minX) * k || 1e-9;
            const h = (maxY - Math.min(...ys)) || 1e-9;
            const s = 80 / Math.max(w, h);
            const ox = (100 - w * s) / 2; const oy = (100 - h * s) / 2;
            return rings.map((r) => r.map((q, i) => (i ? 'L' : 'M') + (ox + (q[0] - minX) * k * s).toFixed(1) + ' ' + (oy + (maxY - q[1]) * s).toFixed(1)).join(' ') + ' Z').join(' ');
        },

        init() {
            ['selected', 'search', 'district', 'assetType'].forEach((key) => this.$watch(key, () => this.redraw(true)));
            const token = @js(config('services.mapbox.token'));
            if (! token || ! window.loadMapbox) return;
            window.loadMapbox().then((mapboxgl) => this.draw(mapboxgl, token))
                .catch(() => console.warn('[Sakuki] mapbox-gl failed to load; the explorer map is unavailable but the page is not.'));
        },

        collections() {
            const lead = (p) => (this.selected.length ? (p.owners.find((o) => this.selected.includes(o.id)) ?? p.owners[0]) : p.owners[0]);
            const shapes = [];
            const points = [];
            for (const p of this.filtered) {
                if (! p.geometry) continue;
                const props = { id: p.id, no: String(p.parcel_no ?? ''), color: this.owner(lead(p).id).color, shared: p.owners.length > 1 };
                shapes.push({ type: 'Feature', geometry: p.geometry, properties: props });
                const c = centre(p.geometry);
                if (c) points.push({ type: 'Feature', geometry: { type: 'Point', coordinates: c }, properties: props });
            }
            return { shapes: { type: 'FeatureCollection', features: shapes }, points: { type: 'FeatureCollection', features: points } };
        },

        draw(mapboxgl, token) {
            this.mapboxgl = mapboxgl;
            mapboxgl.accessToken = token;
            const map = new mapboxgl.Map({ container: this.$refs.map, style: 'mapbox://styles/mapbox/satellite-streets-v12', center: [46.7, 24.7], zoom: 9, attributionControl: false });
            this.map = map;
            map.addControl(new mapboxgl.NavigationControl({ showCompass: true }), 'bottom-left');
            map.addControl(window.sakukiBasemapToggle({
                street: () => document.documentElement.classList.contains('dark') ? 'mapbox://styles/mapbox/dark-v11' : 'mapbox://styles/mapbox/light-v11',
                startOnSatellite: true,
                labels: { streets: labels.streets, satellite: labels.satellite },
            }), 'top-left');

            let framed = false;
            // Every style load — the first and each basemap switch, which wipes layers.
            map.on('style.load', () => {
                const { shapes, points } = this.collections();
                const focus = ['==', ['get', 'id'], this.focused ?? -1];
                map.addSource('ex-shapes', { type: 'geojson', data: shapes });
                map.addSource('ex-points', { type: 'geojson', data: points });
                map.addLayer({ id: 'ex-fill', type: 'fill', source: 'ex-shapes', paint: { 'fill-color': ['get', 'color'], 'fill-opacity': ['case', focus, 0.75, 0.5] } });
                map.addLayer({ id: 'ex-glow', type: 'line', source: 'ex-shapes', paint: { 'line-color': ['get', 'color'], 'line-width': 9, 'line-blur': 7, 'line-opacity': 0.75 } });
                // Shared parcels draw a dashed edge, so co-ownership shows at a glance.
                map.addLayer({ id: 'ex-edge', type: 'line', source: 'ex-shapes', filter: ['!', ['get', 'shared']], paint: { 'line-color': '#ffffff', 'line-width': 1.6 } });
                map.addLayer({ id: 'ex-edge-shared', type: 'line', source: 'ex-shapes', filter: ['get', 'shared'], paint: { 'line-color': '#ffffff', 'line-width': 2, 'line-dasharray': [2, 1.5] } });
                map.addLayer({ id: 'ex-focus', type: 'line', source: 'ex-shapes', filter: focus, paint: { 'line-color': '#ffe08f', 'line-width': 4 } });
                // A pin for every parcel, so even a small one is seen from far out; it fades as the shapes take over.
                map.addLayer({ id: 'ex-pulse', type: 'circle', source: 'ex-points', filter: focus, paint: { 'circle-color': '#ffe08f', 'circle-opacity': 0.35, 'circle-radius': 18 } });
                map.addLayer({ id: 'ex-pin', type: 'circle', source: 'ex-points', paint: {
                    'circle-color': ['get', 'color'], 'circle-stroke-color': '#ffffff', 'circle-stroke-width': 2.5,
                    'circle-radius': ['interpolate', ['linear'], ['zoom'], 5, 9, 12, 8, 15, 6, 17, 0],
                    'circle-opacity': ['interpolate', ['linear'], ['zoom'], 14, 1, 16.5, 0],
                    'circle-stroke-opacity': ['interpolate', ['linear'], ['zoom'], 14, 1, 16.5, 0],
                } });
                map.addLayer({ id: 'ex-label', type: 'symbol', source: 'ex-points', minzoom: 13.5, layout: { 'text-field': ['get', 'no'], 'text-size': 13, 'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'] }, paint: { 'text-color': '#ffffff', 'text-halo-color': '#0b1c30', 'text-halo-width': 2 } });

                if (framed) return;
                framed = true;
                this.fitAll(0);
                ['ex-fill', 'ex-pin'].forEach((layer) => {
                    map.on('click', layer, (e) => this.focus(Number(e.features[0].properties.id), false, e.lngLat));
                    map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer'; });
                    map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = ''; });
                });
                this.animatePulse();
            });
        },

        redraw(refit = false) {
            if (! this.map || ! this.map.getSource('ex-shapes')) return;
            const { shapes, points } = this.collections();
            const focus = ['==', ['get', 'id'], this.focused ?? -1];
            this.map.getSource('ex-shapes').setData(shapes);
            this.map.getSource('ex-points').setData(points);
            this.map.setFilter('ex-focus', focus);
            this.map.setFilter('ex-pulse', focus);
            this.map.setPaintProperty('ex-fill', 'fill-opacity', ['case', focus, 0.75, 0.5]);
            if (refit) this.fitAll();
        },

        fitAll(duration = 800) {
            if (! this.map) return;
            const pts = this.filtered.flatMap((p) => pointsOf(p.geometry));
            if (! pts.length) return;
            const b = new this.mapboxgl.LngLatBounds();
            pts.forEach((c) => b.extend(c));
            this.map.fitBounds(b, { padding: 70, maxZoom: 16.5, duration });
        },

        /** Bring one parcel forward: on the map, in the list, and in a card on the map. */
        focus(id, scroll = true, at = null) {
            this.focused = id;
            const p = data.parcels.find((x) => x.id === id);
            if (! p) return;
            this.redraw(false);
            if (this.map && p.geometry) {
                const b = new this.mapboxgl.LngLatBounds();
                pointsOf(p.geometry).forEach((c) => b.extend(c));
                this.map.fitBounds(b, { padding: 120, maxZoom: 17, duration: 900 });
                this.popup(p, at ?? centre(p.geometry));
            }
            if (scroll) this.$refs.map.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        // Built with DOM nodes, never innerHTML, so nothing in the data is read as markup.
        popup(p, lngLat) {
            this.currentPopup?.remove();
            const box = document.createElement('div');
            box.dir = document.documentElement.dir;
            box.style.cssText = 'font-family: inherit; font-size: 12px; color: #0b1c30; min-width: 200px;';
            const title = document.createElement('div');
            title.style.cssText = 'font-size: 16px; font-weight: 800; margin-bottom: 2px;';
            title.textContent = p.parcel_no ?? '—';
            const place = document.createElement('div');
            place.style.cssText = 'color: #5b6b82; margin-bottom: 6px;';
            place.textContent = [p.district, p.city].filter(Boolean).join(' · ');
            box.append(title, place);
            p.owners.forEach((o) => {
                const row = document.createElement('div');
                row.style.cssText = 'display: flex; align-items: center; gap: 6px; padding: 2px 0;';
                const dot = document.createElement('span');
                dot.style.cssText = `width: 10px; height: 10px; border-radius: 999px; background: ${this.owner(o.id).color}; flex-shrink: 0;`;
                const name = document.createElement('span');
                name.textContent = this.owner(o.id).name + (o.share < 0.9999 ? ' — ' + this.pct(o.share) : '');
                row.append(dot, name);
                box.append(row);
            });
            const figures = document.createElement('div');
            figures.style.cssText = 'margin-top: 6px; font-weight: 700;';
            figures.textContent = this.fmt(p.area) + ' ' + labels.sqm + (showValue && p.value !== null ? '  ·  ' + this.fmt(p.value) + ' ' + labels.sar : '');
            const link = document.createElement('a');
            link.href = this.url(p.id);
            link.textContent = labels.open;
            link.style.cssText = 'display: block; margin-top: 8px; padding: 6px 10px; border-radius: 10px; background: #002444; color: #fff; text-align: center; font-weight: 600;';
            box.append(figures, link);
            this.currentPopup = new this.mapboxgl.Popup({ maxWidth: '280px', offset: 12 }).setLngLat(lngLat).setDOMContent(box).addTo(this.map);
        },

        /** The ring round the parcel in focus breathes, so the eye finds it. */
        animatePulse() {
            const step = (t) => {
                if (this.map && this.map.getLayer('ex-pulse')) {
                    const k = (Math.sin(t / 350) + 1) / 2;
                    this.map.setPaintProperty('ex-pulse', 'circle-radius', 14 + k * 14);
                    this.map.setPaintProperty('ex-pulse', 'circle-opacity', 0.15 + (1 - k) * 0.35);
                }
                requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
    };
}
</script>
@endpush
