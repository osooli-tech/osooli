<div x-data="splitUpload(@js($types), @js([
        'default_type' => \App\Enums\PhotoType::BoundarySurvey->value,
        'pages' => __('documents.split.pages'),
        'page' => __('documents.split.page'),
        'load_failed' => __('documents.split.load_failed'),
        'done' => __('documents.split.done'),
        'matched' => __('documents.split.matched'),
        'ocr' => __('documents.split.ocr_progress'),
    ]))" class="space-y-4">

    {{-- 1. The file: picked, or dropped anywhere on this card --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6 border shadow-sm transition-colors"
         :class="dragging ? 'border-secondary bg-secondary/5' : 'border-outline-variant dark:border-white/10'"
         x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false"
         x-on:drop.prevent="dragging = false; open($event.dataTransfer.files[0])">
        <h1 class="text-lg font-bold text-on-surface dark:text-white">{{ __('documents.split.title') }}</h1>
        <p class="mt-1 text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('documents.split.subtitle') }}</p>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <input type="file" accept="application/pdf,.pdf" x-on:change="open($event.target.files[0])"
                   class="block text-sm text-on-surface dark:text-white file:me-3 file:px-4 file:py-2 file:rounded-xl file:border-0
                          file:bg-secondary/10 file:text-secondary file:font-medium">
            <span class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('documents.split.or_drop') }}</span>
            <span x-show="loading" x-cloak class="flex items-center gap-2 text-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                <span x-text="progress"></span>
            </span>
        </div>
        <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm text-error"></p>
    </div>

    <template x-if="pages.length">
        <div class="space-y-4">
            {{-- 2. The pages, and which go together --}}
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6 border border-outline-variant dark:border-white/10 shadow-sm">
                <h2 class="mb-1 font-bold text-on-surface dark:text-white">
                    {{ __('documents.split.step_pages') }}
                    <span class="text-sm font-normal text-on-surface-variant" x-text="'(' + pages.length + ')'"></span>
                </h2>
                <p class="mb-2 text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('documents.split.pages_hint') }}</p>
                <p x-show="matchedCount" x-cloak class="mb-3 text-sm text-secondary"
                   x-text="labels.matched.replace(':count', matchedCount).replace(':total', groups().length)"></p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    <template x-for="page in pages" :key="page.n">
                        <div class="rounded-xl border p-2 text-center"
                             :class="page.merge ? 'border-secondary/60 bg-secondary/5' : 'border-outline-variant dark:border-white/10'">
                            <button type="button" x-on:click="enlarge(page.n)" class="block w-full">
                                <img :src="page.thumb" class="mx-auto max-h-44 rounded shadow-sm" :alt="labels.page + ' ' + page.n">
                            </button>
                            <p class="mt-1 text-xs font-semibold text-on-surface dark:text-white" x-text="labels.page + ' ' + page.n"></p>
                            <label x-show="page.n > 1" class="mt-1 flex items-center justify-center gap-1 text-[11px] text-on-surface-variant dark:text-on-primary-container cursor-pointer">
                                <input type="checkbox" x-model="page.merge" class="accent-secondary">
                                {{ __('documents.split.with_previous') }}
                            </label>
                        </div>
                    </template>
                </div>
            </div>

            {{-- 3. Each part: its parcel and its type --}}
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6 border border-outline-variant dark:border-white/10 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-bold text-on-surface dark:text-white">{{ __('documents.split.step_parts') }}</h2>
                    <label class="flex items-center gap-2 text-sm text-on-surface dark:text-white">
                        {{ __('documents.split.type_for_all') }}
                        <select x-on:change="setTypeForAll($event.target.value)" class="rounded-lg border border-outline-variant dark:border-white/10 bg-surface-container-lowest dark:bg-[#252b3b] px-2 py-1 text-sm">
                            <template x-for="type in types" :key="type.value">
                                <option :value="type.value" x-text="type.label" :selected="type.value === labels.default_type"></option>
                            </template>
                        </select>
                    </label>
                </div>
                <p x-show="planNo" x-cloak class="mb-3 text-xs text-on-surface-variant dark:text-on-primary-container">
                    {{ __('documents.split.plan_hint') }} <strong x-text="planNo"></strong>
                </p>

                <div class="space-y-3">
                    <template x-for="group in groups()" :key="group.key">
                        <div class="rounded-xl border border-outline-variant dark:border-white/10 p-4"
                             :class="{ 'border-secondary bg-secondary/5': part(group).status === 'done', 'border-error': part(group).status === 'error' }">
                            <div class="flex flex-wrap items-start gap-4">
                                <div class="flex gap-1">
                                    <template x-for="n in group.pages" :key="n">
                                        <img :src="pages[n - 1].thumb" class="h-20 rounded shadow-sm cursor-pointer" x-on:click="enlarge(n)">
                                    </template>
                                </div>

                                <div class="min-w-[260px] flex-1 space-y-2">
                                    <p class="text-sm font-semibold text-on-surface dark:text-white"
                                       x-text="(group.pages.length > 1 ? labels.pages : labels.page) + ' ' + group.pages.join('، ')"></p>

                                    <p x-show="part(group).parcelId" x-cloak class="flex flex-wrap items-center gap-1 text-sm text-secondary">
                                        <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                        <span x-text="part(group).label"></span>
                                        <span x-show="part(group).auto === 'text'" class="rounded-full bg-secondary/15 px-2 py-0.5 text-[10px] font-semibold">{{ __('documents.split.auto') }}</span>
                                        <span x-show="part(group).auto === 'ocr'" class="rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300 px-2 py-0.5 text-[10px] font-semibold">{{ __('documents.split.auto_ocr') }}</span>
                                        <button type="button" x-on:click="part(group).parcelId = null; part(group).label = ''; part(group).auto = false"
                                                class="ms-1 text-xs text-on-surface-variant underline">{{ __('documents.split.change') }}</button>
                                    </p>

                                    <div x-show="! part(group).parcelId" class="relative">
                                        <input type="text" x-model="part(group).query" x-on:input.debounce.300ms="search(group)"
                                               placeholder="{{ __('documents.split.search_placeholder') }}"
                                               class="w-full rounded-lg border border-outline-variant dark:border-white/10 bg-surface-container-lowest dark:bg-[#252b3b] px-3 py-1.5 text-sm text-on-surface dark:text-white">
                                        <ul x-show="part(group).results.length" x-cloak x-on:click.outside="part(group).results = []"
                                            class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-outline-variant bg-surface-container-lowest dark:bg-[#252b3b] shadow-lg">
                                            <template x-for="option in part(group).results" :key="option.id">
                                                <li x-on:click="choose(group, option)" x-text="option.label"
                                                    class="cursor-pointer px-3 py-1.5 text-sm text-on-surface dark:text-white hover:bg-secondary/10"></li>
                                            </template>
                                        </ul>
                                    </div>
                                    <div x-show="planParcels.length && ! part(group).parcelId" x-cloak class="flex flex-wrap gap-1">
                                        <template x-for="option in planParcels.slice(0, 40)" :key="option.id">
                                            <button type="button" x-on:click="choose(group, option)" x-text="option.label.split(' — ')[0]"
                                                    :title="option.label"
                                                    class="rounded-full border border-outline-variant dark:border-white/10 px-2 py-0.5 text-[11px] text-on-surface dark:text-white hover:bg-secondary/10"></button>
                                        </template>
                                    </div>
                                </div>

                                <select x-model="part(group).type" class="rounded-lg border border-outline-variant dark:border-white/10 bg-surface-container-lowest dark:bg-[#252b3b] px-2 py-1.5 text-sm text-on-surface dark:text-white">
                                    <template x-for="type in types" :key="type.value">
                                        <option :value="type.value" x-text="type.label" :selected="type.value === part(group).type"></option>
                                    </template>
                                </select>

                                <div class="w-24 text-center text-xs">
                                    <span x-show="part(group).status === 'uploading'" class="material-symbols-outlined animate-spin text-secondary">progress_activity</span>
                                    <span x-show="part(group).status === 'done'" class="material-symbols-outlined text-secondary">task_alt</span>
                                    <span x-show="part(group).status === 'error'" class="block text-error" x-text="part(group).message"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <button type="button" x-on:click="upload()" :disabled="busy || ! ready()"
                            class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-sm font-semibold bg-secondary text-white hover:brightness-110 disabled:opacity-50">
                        <span class="material-symbols-outlined text-[18px]" :class="busy && 'animate-spin'" x-text="busy ? 'progress_activity' : 'upload_file'"></span>
                        {{ __('documents.split.upload') }}
                    </button>
                    <span x-show="! ready()" class="text-xs text-on-surface-variant">{{ __('documents.split.need_parcels') }}</span>
                    <span x-show="finished" x-cloak class="text-sm text-secondary" x-text="finished"></span>
                </div>
                <p class="mt-2 text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('documents.split.pending_note') }}</p>
            </div>
        </div>
    </template>

    {{-- A page at reading size --}}
    <div x-show="large" x-cloak x-on:keydown.escape.window="large = null"
         class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/70 p-4" x-on:click.self="large = null">
        <div class="relative max-h-[95vh] overflow-auto rounded-xl bg-white p-2">
            <button type="button" x-on:click="large = null" class="absolute top-2 end-2 rounded-full bg-black/60 p-1 text-white">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
            <img :src="large" class="max-w-[90vw]">
        </div>
    </div>
</div>

@script
<script>
    // pdf.js renders the pages and reads their text; pdf-lib cuts the parts.
    // Both from the CDN, loaded on this screen only, as mapbox-gl is for the map.
    const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
    const PDFLIB = 'https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js';
    // OCR for pages with no text; it fetches its engine and English model
    // from the same CDN the first time, then the browser keeps them.
    const TESSERACT = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';

    const loadScript = (src, global) => window[global] ? Promise.resolve(window[global]) : new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = () => resolve(window[global]);
        s.onerror = () => reject(new Error(src));
        document.head.appendChild(s);
    });

    window.splitUpload = (types, labels) => {
        // The PDF's bytes and pdf.js's handle stay out of Alpine's proxies.
        let bytes = null;
        let pdf = null;

        return {
            types,
            labels,
            fileName: '',
            planNo: '',
            planParcels: [],
            pages: [],
            parts: {},
            typeForAll: labels.default_type,
            loading: false,
            progress: '',
            dragging: false,
            busy: false,
            error: '',
            finished: '',
            large: null,

            get matchedCount() {
                return this.groups().filter((g) => this.part(g).auto).length;
            },

            async open(file) {
                this.error = '';
                this.finished = '';
                this.pages = [];
                this.parts = {};
                if (! file) return;
                if (file.type && file.type !== 'application/pdf') {
                    this.error = labels.load_failed;
                    return;
                }

                this.loading = true;
                try {
                    const pdfjs = await loadScript(PDFJS + 'pdf.min.js', 'pdfjsLib');
                    pdfjs.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js';
                    bytes = new Uint8Array(await file.arrayBuffer());
                    pdf = await pdfjs.getDocument({ data: bytes.slice() }).promise;
                    this.fileName = file.name;

                    // "… مخطط 623.pdf": offer that plan's parcels up front.
                    const plan = file.name.match(/(?:مخطط|plan)\s*[#:]?\s*([\p{L}\d-]+)/iu);
                    this.planNo = plan ? plan[1] : '';
                    this.planParcels = this.planNo ? await $wire.planParcels(this.planNo) : [];

                    const pages = [];
                    for (let n = 1; n <= pdf.numPages; n++) {
                        this.progress = n + ' / ' + pdf.numPages;
                        pages.push({ n, thumb: await this.render(n, 220), merge: false });
                    }
                    this.pages = pages;

                    // Each page's own text, where it has any, names its parcel.
                    const unread = [];
                    for (let n = 1; n <= pdf.numPages; n++) {
                        const page = await pdf.getPage(n);
                        const content = await page.getTextContent();
                        const text = content.items.map((i) => i.str).join(' ');
                        const match = text.trim().length < 20 ? null : await $wire.matchPage(text);
                        match ? this.matched(n, match, 'text') : unread.push(n);
                    }

                    // Pages that are only a picture — a scan, or a map exported
                    // as an image — are read by OCR: digits and dashes only,
                    // which is what a GEO ID such as "133-623" is made of.
                    if (unread.length) {
                        const Tesseract = await loadScript(TESSERACT, 'Tesseract');
                        const worker = await Tesseract.createWorker('eng');
                        await worker.setParameters({ tessedit_char_whitelist: '0123456789-', tessedit_pageseg_mode: '11' });
                        try {
                            for (const [i, n] of unread.entries()) {
                                this.progress = labels.ocr.replace(':done', i + 1).replace(':total', unread.length);
                                const canvas = await this.canvas(n, 2400);
                                const { data } = await worker.recognize(canvas);
                                const match = await $wire.matchPage(data.text);
                                if (match) this.matched(n, match, 'ocr');
                            }
                        } finally {
                            await worker.terminate();
                        }
                    }
                } catch (e) {
                    this.error = labels.load_failed;
                } finally {
                    this.loading = false;
                    this.progress = '';
                }
            },

            async canvas(n, width) {
                const page = await pdf.getPage(n);
                const base = page.getViewport({ scale: 1 });
                const viewport = page.getViewport({ scale: width / base.width });
                const canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                // "print" draws without requestAnimationFrame, which browsers
                // pause in a background tab — so reading a long file carries on
                // while the person looks at something else.
                await page.render({ canvasContext: canvas.getContext('2d'), viewport, intent: 'print' }).promise;
                return canvas;
            },

            async render(n, width) {
                return (await this.canvas(n, width)).toDataURL('image/jpeg', 0.8);
            },

            /** A page matched on its own, from its text or by OCR. */
            matched(n, match, how) {
                Object.assign(this.part({ key: n }), { parcelId: match.id, label: match.label, auto: how });
            },

            async enlarge(n) {
                this.large = await this.render(n, 1100);
            },

            /** Pages grouped: each page starts a part unless it goes with the one before. */
            groups() {
                const groups = [];
                this.pages.forEach((page) => {
                    if (page.merge && groups.length) {
                        groups[groups.length - 1].pages.push(page.n);
                    } else {
                        groups.push({ key: page.n, pages: [page.n] });
                    }
                });
                return groups;
            },

            /** A part's choices, kept by its first page so regrouping keeps them. */
            part(group) {
                if (! this.parts[group.key]) {
                    this.parts[group.key] = {
                        parcelId: null, label: '', auto: false, query: '', results: [],
                        type: this.typeForAll, status: '', message: '',
                    };
                }
                return this.parts[group.key];
            },

            setTypeForAll(type) {
                this.typeForAll = type;
                this.groups().forEach((g) => { if (this.part(g).status !== 'done') this.part(g).type = type; });
            },

            async search(group) {
                const part = this.part(group);
                part.results = part.query.trim() === '' ? [] : await $wire.searchParcels(part.query);
            },

            choose(group, option) {
                Object.assign(this.part(group), { parcelId: option.id, label: option.label, auto: false, results: [], query: '' });
            },

            ready() {
                return this.groups().every((g) => this.part(g).parcelId || this.part(g).status === 'done');
            },

            async upload() {
                if (! this.ready()) return;
                this.busy = true;
                this.finished = '';
                const PDFLib = await loadScript(PDFLIB, 'PDFLib');
                const source = await PDFLib.PDFDocument.load(bytes);
                let done = 0;

                for (const group of this.groups()) {
                    const part = this.part(group);
                    if (part.status === 'done') { done++; continue; }
                    part.status = 'uploading';
                    try {
                        const out = await PDFLib.PDFDocument.create();
                        const copied = await out.copyPages(source, group.pages.map((n) => n - 1));
                        copied.forEach((p) => out.addPage(p));
                        const file = new File([await out.save()], 'part.pdf', { type: 'application/pdf' });

                        await new Promise((resolve, reject) => $wire.upload('piece', file, resolve, reject));
                        await $wire.savePiece({
                            parcel_id: part.parcelId,
                            photo_type: part.type,
                            pages: group.pages.length > 1 ? group.pages[0] + '-' + group.pages[group.pages.length - 1] : String(group.pages[0]),
                            source: this.fileName,
                        });
                        part.status = 'done';
                        done++;
                    } catch (e) {
                        part.status = 'error';
                        part.message = e?.message ?? '';
                    }
                }

                this.busy = false;
                this.finished = labels.done.replace(':count', done);
            },
        };
    };
</script>
@endscript
