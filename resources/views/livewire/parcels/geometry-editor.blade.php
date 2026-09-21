<div>
    @if ($show && $parcel)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-2 sm:p-4">

            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="close"></div>

            <div x-data="parcelGeometryEditor(@js($config))"
                 wire:key="geometry-editor-{{ $parcel->id }}"
                 data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-6xl max-h-[95vh] overflow-y-auto
                        bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-5 space-y-4 outline-none">

                {{-- Header --}}
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-on-surface dark:text-white">
                            {{ $config['geometry'] ? __('parcels.geometry_edit_title') : __('parcels.geometry_draw_title') }}
                        </h2>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular mt-0.5">
                            {{ __('parcels.parcel_no') }} {{ $parcel->parcel_no ?: $parcel->geo_id }}
                        </p>
                    </div>
                    <button type="button" wire:click="close" aria-label="{{ __('common.cancel') }}"
                            class="p-1.5 rounded-lg text-on-surface-variant dark:text-on-primary-container
                                   hover:bg-surface-container dark:hover:bg-white/10 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- The importer rewrites geom for every parcel it reads, so an
                     edit here lasts only until the next import from QGIS. --}}
                <p class="flex items-start gap-2 text-xs rounded-xl px-3 py-2
                          bg-tertiary/10 text-tertiary dark:bg-tertiary/20 dark:text-white/90">
                    <span class="material-symbols-outlined text-[16px] shrink-0">sync_problem</span>
                    {{ __('parcels.geometry_import_warning') }}
                </p>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                    {{-- Map --}}
                    <div class="lg:col-span-2 relative">
                        <div wire:ignore x-ref="map"
                             class="h-[62vh] min-h-[360px] w-full rounded-xl overflow-hidden
                                    border border-outline-variant dark:border-white/10 bg-surface-container dark:bg-white/5"></div>

                        <div x-show="status === 'loading'"
                             class="absolute inset-0 flex items-center justify-center gap-2 text-sm
                                    text-on-surface-variant dark:text-on-primary-container">
                            <span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                            {{ __('parcels.geometry_map_loading') }}
                        </div>

                        <div x-show="status === 'failed'" x-cloak
                             class="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-sm
                                    text-on-surface-variant dark:text-on-primary-container">
                            <span class="material-symbols-outlined text-[36px] opacity-40">map</span>
                            <span x-text="failMessage"></span>
                        </div>
                    </div>

                    {{-- Side panel --}}
                    <div class="space-y-4 text-sm">

                        {{-- Areas: the drawn figure next to the ones on paper, so a
                             polygon that is plainly the wrong size is noticed here. --}}
                        <div class="rounded-xl border border-outline-variant dark:border-white/10 p-3 space-y-2">
                            <div class="flex justify-between gap-2">
                                <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.geometry_drawn_area') }}</span>
                                <span class="font-semibold text-secondary data-tabular">
                                    <span x-text="formatArea(area)"></span> {{ __('dashboard.area_unit_sqm') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.geometry_parts') }}</span>
                                <span class="font-medium text-on-surface dark:text-white data-tabular" x-text="parts"></span>
                            </div>
                            @if ($deedArea)
                                <div class="flex justify-between gap-2">
                                    <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.area_deed') }}</span>
                                    <span class="font-medium text-on-surface dark:text-white data-tabular">
                                        {{ number_format((float) $deedArea, 2) }} {{ __('dashboard.area_unit_sqm') }}
                                        <span class="text-xs" x-text="difference({{ (float) $deedArea }})"></span>
                                    </span>
                                </div>
                            @endif
                            @if ($measuredArea)
                                <div class="flex justify-between gap-2">
                                    <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.measured_area') }}</span>
                                    <span class="font-medium text-on-surface dark:text-white data-tabular">
                                        {{ number_format((float) $measuredArea, 2) }} {{ __('dashboard.area_unit_sqm') }}
                                        <span class="text-xs" x-text="difference({{ (float) $measuredArea }})"></span>
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Tools --}}
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" x-on:click="startDrawing()" x-bind:disabled="status !== 'ready'"
                                    class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium
                                           border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                                           hover:bg-surface-container dark:hover:bg-white/5 transition-colors disabled:opacity-50">
                                <span class="material-symbols-outlined text-[16px]">pentagon</span>
                                {{ __('parcels.geometry_draw_new') }}
                            </button>
                            <button type="button" x-on:click="removeSelected()" x-bind:disabled="status !== 'ready'"
                                    class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium
                                           border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                                           hover:bg-error/10 hover:text-error transition-colors disabled:opacity-50">
                                <span class="material-symbols-outlined text-[16px]">delete</span>
                                {{ __('parcels.geometry_delete_selected') }}
                            </button>
                            <button type="button" x-on:click="reset()" x-bind:disabled="status !== 'ready'"
                                    class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium
                                           border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                                           hover:bg-surface-container dark:hover:bg-white/5 transition-colors disabled:opacity-50">
                                <span class="material-symbols-outlined text-[16px]">undo</span>
                                {{ __('parcels.geometry_reset') }}
                            </button>
                            <button type="button" x-on:click="pasteOpen = ! pasteOpen" x-bind:disabled="status !== 'ready'"
                                    class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium
                                           border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                                           hover:bg-surface-container dark:hover:bg-white/5 transition-colors disabled:opacity-50">
                                <span class="material-symbols-outlined text-[16px]">data_object</span>
                                {{ __('parcels.geometry_paste') }}
                            </button>
                        </div>

                        <div x-show="pasteOpen" x-cloak class="space-y-2">
                            <textarea x-model="pasteText" rows="5" dir="ltr"
                                      placeholder='{"type":"Polygon","coordinates":[[[46.1,24.6],...]]}'
                                      class="w-full rounded-xl border border-outline-variant dark:border-white/10
                                             bg-surface-container-lowest dark:bg-[#252b3b] text-xs font-mono p-2
                                             text-on-surface dark:text-white"></textarea>
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.geometry_paste_hint') }}</p>
                            <p x-show="pasteError" x-text="pasteError" class="text-xs text-error"></p>
                            <button type="button" x-on:click="applyPaste()"
                                    class="w-full px-3 py-1.5 rounded-xl text-xs font-medium bg-primary text-white hover:bg-primary/90">
                                {{ __('parcels.geometry_paste_apply') }}
                            </button>
                        </div>

                        <ul class="text-xs text-on-surface-variant dark:text-on-primary-container space-y-1 list-disc ps-4">
                            <li>{{ __('parcels.geometry_help.select') }}</li>
                            <li>{{ __('parcels.geometry_help.move') }}</li>
                            <li>{{ __('parcels.geometry_help.add') }}</li>
                            <li>{{ __('parcels.geometry_help.remove') }}</li>
                            <li>{{ __('parcels.geometry_help.finish') }}</li>
                        </ul>

                        {{-- Errors --}}
                        <p x-show="clientError" x-text="clientError" x-cloak
                           class="text-xs rounded-xl px-3 py-2 bg-error/10 text-error"></p>
                        @error('geometry')
                            <p class="text-xs rounded-xl px-3 py-2 bg-error/10 text-error">{{ $message }}</p>
                        @enderror

                        {{-- Overlap: saved only after an explicit second click --}}
                        @if ($overlaps !== [])
                            <div class="rounded-xl px-3 py-2 space-y-2 bg-error/10 text-error text-xs">
                                <p>{{ __('parcels.geometry_overlap', ['parcels' => implode('، ', $overlaps)]) }}</p>
                                <button type="button" x-on:click="save(true)"
                                        class="px-3 py-1 rounded-lg font-medium bg-error text-white hover:brightness-110">
                                    {{ __('parcels.geometry_save_anyway') }}
                                </button>
                            </div>
                        @endif

                        {{-- History --}}
                        @if ($revisions !== [])
                            <div class="space-y-2">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.geometry_history') }}
                                </p>
                                <ul class="divide-y divide-outline-variant dark:divide-white/10 rounded-xl
                                           border border-outline-variant dark:border-white/10 overflow-hidden">
                                    @foreach ($revisions as $revision)
                                        <li class="flex items-center gap-2 px-3 py-2 text-xs">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-on-surface dark:text-white">
                                                    {{ __('parcels.geometry_actions.'.$revision->action) }}
                                                    — {{ $revision->user_name ?? '—' }}
                                                </p>
                                                <p class="text-on-surface-variant dark:text-on-primary-container data-tabular">
                                                    {{ \Illuminate\Support\Carbon::parse($revision->created_at)->format('Y-m-d H:i') }}
                                                    ·
                                                    @if ($revision->has_geom)
                                                        {{ number_format((float) $revision->area_sqm, 0) }} {{ __('dashboard.area_unit_sqm') }}
                                                    @else
                                                        {{ __('parcels.geometry_none') }}
                                                    @endif
                                                </p>
                                            </div>
                                            <button type="button"
                                                    wire:click="restore({{ $revision->id }})"
                                                    wire:confirm="{{ __('parcels.geometry_restore_confirm') }}"
                                                    class="shrink-0 px-2 py-1 rounded-lg font-medium text-primary hover:bg-primary/10">
                                                {{ __('parcels.geometry_restore') }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.geometry_history_hint') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Buttons --}}
                <div class="flex justify-end gap-3 pt-1">
                    <button type="button" wire:click="close"
                            class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                   text-on-surface-variant dark:text-on-primary-container
                                   hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="button" x-on:click="save(false)"
                            x-bind:disabled="status !== 'ready'"
                            wire:loading.attr="disabled" wire:target="save, restore"
                            class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl
                                   bg-secondary text-white hover:brightness-110 transition-all disabled:opacity-60">
                        <span wire:loading wire:target="save, restore"
                              class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                        {{ __('parcels.geometry_save') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    // Mapbox GL Draw comes from the same CDN as mapbox-gl itself, loaded on
    // first use only: most visits to a parcel never open this editor.
    const DRAW_BASE = 'https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/';

    const loadDraw = () => {
        if (window.MapboxDraw) return Promise.resolve(window.MapboxDraw);
        if (window.__mapboxDrawLoading) return window.__mapboxDrawLoading;

        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = DRAW_BASE + 'mapbox-gl-draw.css';
        document.head.appendChild(css);

        window.__mapboxDrawLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = DRAW_BASE + 'mapbox-gl-draw.js';
            script.onload = () => resolve(window.MapboxDraw);
            script.onerror = () => { window.__mapboxDrawLoading = null; reject(new Error('mapbox-gl-draw failed to load')); };
            document.head.appendChild(script);
        });

        return window.__mapboxDrawLoading;
    };

    // Geodesic ring area, the same formula as @mapbox/geojson-area. Only a
    // preview: the figure that counts is PostGIS's, computed on save.
    const RADIUS = 6378137;
    const rad = (degrees) => degrees * Math.PI / 180;
    const ringArea = (ring) => {
        const n = ring.length;
        if (n < 3) return 0;
        let total = 0;
        for (let i = 0; i < n; i++) {
            const p1 = ring[i];
            const p2 = ring[(i + 1) % n];
            const p3 = ring[(i + 2) % n];
            total += (rad(p3[0]) - rad(p1[0])) * Math.sin(rad(p2[1]));
        }
        return Math.abs(total * RADIUS * RADIUS / 2);
    };
    const polygonArea = (rings) => rings.reduce((sum, ring, i) => sum + (i === 0 ? 1 : -1) * ringArea(ring), 0);

    const toPolygons = (geometry) => {
        if (!geometry) return [];
        if (geometry.type === 'Polygon') return [geometry.coordinates];
        if (geometry.type === 'MultiPolygon') return geometry.coordinates;
        return [];
    };
    const unwrap = (data) => {
        if (!data || typeof data !== 'object') return [];
        if (data.type === 'FeatureCollection') return (data.features || []).map((f) => f && f.geometry);
        if (data.type === 'Feature') return [data.geometry];
        return [data];
    };

    window.parcelGeometryEditor = (config) => {
        // Kept out of Alpine's reactive state on purpose: wrapping the map or
        // the draw control in a Proxy breaks their internal identity checks.
        let map = null;
        let draw = null;
        const original = config.geometry ? JSON.parse(config.geometry) : null;

        return {
            status: 'loading',
            failMessage: config.i18n.map_failed,
            area: 0,
            parts: 0,
            clientError: '',
            pasteOpen: false,
            pasteText: '',
            pasteError: '',

            init() {
                if (!config.token || !window.loadMapbox) {
                    this.status = 'failed';
                    return;
                }

                Promise.all([window.loadMapbox(), loadDraw()])
                    .then(([mapboxgl, MapboxDraw]) => this.boot(mapboxgl, MapboxDraw))
                    .catch(() => { this.status = 'failed'; });
            },

            destroy() {
                if (map) map.remove();
                map = null;
                draw = null;
            },

            boot(mapboxgl, MapboxDraw) {
                mapboxgl.accessToken = config.token;
                map = new mapboxgl.Map({
                    container: this.$refs.map,
                    style: 'mapbox://styles/mapbox/satellite-streets-v12',
                    center: [45.0, 24.5],
                    zoom: 12,
                    attributionControl: false,
                });
                draw = new MapboxDraw({ displayControlsDefault: false, controls: { polygon: true, trash: true } });

                map.addControl(new mapboxgl.NavigationControl(), 'bottom-left');
                map.addControl(draw, 'top-left');

                map.on('load', () => {
                    this.addNeighbours();
                    this.load(original ? toPolygons(original) : []);
                    this.fit();
                    if (!original) draw.changeMode('draw_polygon');
                    this.status = 'ready';
                });

                ['draw.create', 'draw.update', 'draw.delete'].forEach((name) => map.on(name, () => this.measure()));
            },

            addNeighbours() {
                let neighbours = null;
                try { neighbours = JSON.parse(config.neighbours); } catch (e) { neighbours = null; }
                if (!neighbours || !neighbours.features || !neighbours.features.length) return;

                map.addSource('neighbours', { type: 'geojson', data: neighbours });
                map.addLayer({ id: 'neighbours-fill', type: 'fill', source: 'neighbours',
                    paint: { 'fill-color': '#94a3b8', 'fill-opacity': 0.15 } });
                map.addLayer({ id: 'neighbours-outline', type: 'line', source: 'neighbours',
                    paint: { 'line-color': '#e2e8f0', 'line-width': 1 } });
                map.addLayer({ id: 'neighbours-labels', type: 'symbol', source: 'neighbours',
                    layout: {
                        'text-field': ['to-string', ['get', 'parcel_no']],
                        'text-size': 11,
                        'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    },
                    paint: { 'text-color': '#ffffff', 'text-halo-color': '#0f172a', 'text-halo-width': 1.2 } });
            },

            load(polygons) {
                draw.deleteAll();
                polygons.forEach((coordinates) => draw.add({
                    type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates },
                }));
                this.measure();
            },

            fit() {
                const coords = this.collectPolygons().flat(2);
                if (coords.length) {
                    const lngs = coords.map((c) => c[0]);
                    const lats = coords.map((c) => c[1]);
                    map.fitBounds([[Math.min(...lngs), Math.min(...lats)], [Math.max(...lngs), Math.max(...lats)]],
                        { padding: 60, maxZoom: 19, duration: 0 });
                } else if (config.bounds) {
                    const [x1, y1, x2, y2] = config.bounds;
                    map.fitBounds([[x1, y1], [x2, y2]], { padding: 40, maxZoom: 18, duration: 0 });
                }
            },

            collectPolygons() {
                if (!draw) return [];
                return draw.getAll().features
                    .flatMap((feature) => toPolygons(feature.geometry))
                    .filter((rings) => rings.length && rings[0].length >= 4);
            },

            measure() {
                const polygons = this.collectPolygons();
                this.parts = polygons.length;
                this.area = polygons.reduce((sum, rings) => sum + polygonArea(rings), 0);
                this.clientError = '';
            },

            formatArea(value) {
                return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 2 });
            },

            difference(reference) {
                if (!reference || !this.area) return '';
                const percent = ((this.area - reference) / reference) * 100;
                return '(' + (percent >= 0 ? '+' : '') + percent.toFixed(1) + '%)';
            },

            startDrawing() {
                draw.changeMode('draw_polygon');
            },

            removeSelected() {
                draw.trash();
                this.measure();
            },

            reset() {
                this.load(original ? toPolygons(original) : []);
                this.fit();
            },

            applyPaste() {
                try {
                    const polygons = unwrap(JSON.parse(this.pasteText)).flatMap(toPolygons);
                    if (!polygons.length) throw new Error('no polygons');
                    this.load(polygons);
                    this.fit();
                    this.pasteOpen = false;
                    this.pasteText = '';
                    this.pasteError = '';
                } catch (e) {
                    this.pasteError = config.i18n.paste_invalid;
                }
            },

            save(confirmOverlap) {
                // Leaving draw mode finishes a polygon still being drawn (or
                // drops it, if it has too few points to be one).
                draw.changeMode('simple_select');
                this.measure();

                const polygons = this.collectPolygons();
                if (!polygons.length) {
                    this.clientError = config.i18n.empty;
                    return;
                }

                $wire.save(JSON.stringify({ type: 'MultiPolygon', coordinates: polygons }), confirmOverlap === true);
            },
        };
    };
</script>
@endscript
