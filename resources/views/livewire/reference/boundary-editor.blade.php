<div>
    @if ($show && $record)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-2 sm:p-4">

            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="close"></div>

            <div x-data="boundaryEditor(@js($config))"
                 wire:key="boundary-editor-{{ $level }}-{{ $record->id }}"
                 data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-6xl max-h-[95vh] overflow-y-auto
                        bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-5 space-y-4 outline-none">

                {{-- Header --}}
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-on-surface dark:text-white">
                            {{ __('boundaries.title.'.$level) }} — {{ app()->isLocale('en') && $record->name_en ? $record->name_en : $record->name_ar }}
                        </h2>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5">
                            {{ __('boundaries.source_label') }}:
                            {{ __('boundaries.sources.'.($record->boundary_source ?? 'none')) }}
                        </p>
                    </div>
                    <button type="button" wire:click="close" aria-label="{{ __('common.cancel') }}"
                            class="p-1.5 rounded-lg text-on-surface-variant dark:text-on-primary-container
                                   hover:bg-surface-container dark:hover:bg-white/10 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <p class="flex items-start gap-2 text-xs rounded-xl px-3 py-2
                          bg-tertiary/10 text-tertiary dark:bg-tertiary/20 dark:text-white/90">
                    <span class="material-symbols-outlined text-[16px] shrink-0">info</span>
                    {{ __('boundaries.manual_note') }}
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
                        <div class="rounded-xl border border-outline-variant dark:border-white/10 p-3 space-y-2">
                            <div class="flex justify-between gap-2">
                                <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('boundaries.area') }}</span>
                                <span class="font-semibold text-secondary data-tabular">
                                    <span x-text="formatArea(area)"></span> {{ __('boundaries.km2') }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.geometry_parts') }}</span>
                                <span class="font-medium text-on-surface dark:text-white data-tabular" x-text="parts"></span>
                            </div>
                            <div class="flex justify-between gap-2">
                                <span class="text-on-surface-variant dark:text-on-primary-container">{{ __('boundaries.vertices') }}</span>
                                <span class="font-medium text-on-surface dark:text-white data-tabular" x-text="vertices"></span>
                            </div>
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
                            <li>{{ __('boundaries.neighbours_hint') }}</li>
                        </ul>

                        <p x-show="clientError" x-text="clientError" x-cloak
                           class="text-xs rounded-xl px-3 py-2 bg-error/10 text-error"></p>
                        @error('geometry')
                            <p class="text-xs rounded-xl px-3 py-2 bg-error/10 text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Buttons --}}
                <div class="flex flex-wrap justify-between gap-3 pt-1">
                    <div>
                        @if ($config['geometry'])
                            <button type="button" wire:click="remove"
                                    wire:confirm="{{ __('boundaries.remove_confirm') }}"
                                    class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl text-error hover:bg-error/10 transition-colors">
                                <span class="material-symbols-outlined text-[16px]">delete_forever</span>
                                {{ __('boundaries.remove') }}
                            </button>
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <button type="button" wire:click="close"
                                class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                       text-on-surface-variant dark:text-on-primary-container
                                       hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="button" x-on:click="save()"
                                x-bind:disabled="status !== 'ready'"
                                wire:loading.attr="disabled" wire:target="save, remove"
                                class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl
                                       bg-secondary text-white hover:brightness-110 transition-all disabled:opacity-60">
                            <span wire:loading wire:target="save, remove"
                                  class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            {{ __('boundaries.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@script
<script>
    // The same Mapbox GL Draw build the parcel editor loads, shared through
    // the same window promise so it is fetched once per page at most.
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

    // Geodesic ring area, as in the parcel editor — a preview only.
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

    window.boundaryEditor = (config) => {
        // Outside Alpine's reactive state: a Proxy breaks the map's internals.
        let map = null;
        let draw = null;
        const original = config.geometry ? JSON.parse(config.geometry) : null;

        return {
            status: 'loading',
            failMessage: config.i18n.map_failed,
            area: 0,
            parts: 0,
            vertices: 0,
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
                    style: 'mapbox://styles/mapbox/streets-v12',
                    center: [45.0, 24.5],
                    zoom: 5,
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
                    paint: { 'fill-color': '#64748b', 'fill-opacity': 0.12 } });
                map.addLayer({ id: 'neighbours-outline', type: 'line', source: 'neighbours',
                    paint: { 'line-color': '#475569', 'line-width': 1 } });
                map.addLayer({ id: 'neighbours-labels', type: 'symbol', source: 'neighbours',
                    layout: {
                        'text-field': ['get', 'name'],
                        'text-size': 11,
                        'text-font': ['Open Sans Bold', 'Arial Unicode MS Bold'],
                    },
                    paint: { 'text-color': '#334155', 'text-halo-color': '#ffffff', 'text-halo-width': 1.2 } });
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
                    let [x1, y1, x2, y2] = [Infinity, Infinity, -Infinity, -Infinity];
                    // A loop, not Math.min(...spread): a region has too many
                    // vertices to pass as arguments.
                    coords.forEach(([x, y]) => { x1 = Math.min(x1, x); y1 = Math.min(y1, y); x2 = Math.max(x2, x); y2 = Math.max(y2, y); });
                    map.fitBounds([[x1, y1], [x2, y2]], { padding: 40, maxZoom: 17, duration: 0 });
                } else if (config.bounds) {
                    const [x1, y1, x2, y2] = config.bounds;
                    map.fitBounds([[x1, y1], [x2, y2]], { padding: 40, maxZoom: 16, duration: 0 });
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
                this.vertices = polygons.reduce((sum, rings) => sum + rings.reduce((s, ring) => s + ring.length, 0), 0);
                this.area = polygons.reduce((sum, rings) => sum + polygonArea(rings), 0) / 1e6;
                this.clientError = '';
            },

            formatArea(value) {
                return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 3 });
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

            save() {
                // Leaving draw mode finishes a polygon still being drawn.
                draw.changeMode('simple_select');
                this.measure();

                const polygons = this.collectPolygons();
                if (!polygons.length) {
                    this.clientError = config.i18n.empty;
                    return;
                }

                $wire.save(JSON.stringify({ type: 'MultiPolygon', coordinates: polygons }));
            },
        };
    };
</script>
@endscript
