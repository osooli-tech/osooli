@props([
    'name',
    'label',
    'source',
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'hint' => null,
    // Livewire property holding the parent id to narrow results by
    // (e.g. only the districts of the chosen city).
    'parent' => null,
    // Filters apply as soon as a value is picked; form fields wait for save.
    'live' => false,
])

{{-- A <select> that searches as you type. The options come from the server
     (ReferenceOptions), a few at a time, because a list of every city or
     district in the kingdom is thousands of entries long. --}}
<div x-data="{
        value: $wire.entangle(@js($name), @js((bool) $live)),
        label: @js(\App\Support\ReferenceOptions::label($source, $value)),
        query: '',
        open: false,
        loading: false,
        results: [],
        active: -1,
        timer: null,
        init() {
            this.$watch('value', (v) => { if (v === '' || v === null) this.label = ''; });
        },
        openList() {
            if (this.open) return;
            this.open = true;
            this.query = '';
            this.search();
            this.$nextTick(() => this.$refs.input.select());
        },
        close() { this.open = false; this.query = ''; this.active = -1; },
        typed() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(), 250);
        },
        async search() {
            this.loading = true;
            const params = new URLSearchParams({ q: this.query });
            @if ($parent)
                const parent = $wire.get(@js($parent));
                if (parent) params.set('parent', parent);
            @endif
            try {
                const response = await fetch(@js(route('reference.options', $source, false)) + '?' + params, {
                    headers: { Accept: 'application/json' },
                });
                this.results = response.ok ? await response.json() : [];
            } catch (e) {
                this.results = [];
            }
            this.loading = false;
            this.active = this.results.length ? 0 : -1;
        },
        pick(option) {
            this.value = String(option.id);
            this.label = option.label;
            this.close();
        },
        clear() { this.value = ''; this.label = ''; },
        move(step) {
            if (!this.open) return this.openList();
            if (!this.results.length) return;
            this.active = (this.active + step + this.results.length) % this.results.length;
        },
        enter() {
            if (this.open && this.results[this.active]) this.pick(this.results[this.active]);
        },
     }"
     @click.outside="close()"
     class="relative">

    <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
        {{ $label }}
        @if ($required)
            <span class="text-error">*</span>
        @endif
    </label>

    <div class="relative">
        <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                     text-on-surface-variant dark:text-on-primary-container pointer-events-none">search</span>

        <input x-ref="input"
               type="text"
               autocomplete="off"
               :value="open ? query : label"
               @input="query = $event.target.value; typed()"
               @focus="openList()"
               @click="openList()"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.enter.prevent="enter()"
               @keydown.escape.prevent="close(); $refs.input.blur()"
               @keydown.tab="close()"
               placeholder="{{ $placeholder ?? __('common.search_choose') }}"
               {{ $attributes->class([
                   'w-full ps-9 pe-9 py-2 text-sm rounded-xl',
                   'bg-surface-container dark:bg-[#252b3b]',
                   'border border-outline-variant dark:border-white/10',
                   'text-on-surface dark:text-white placeholder:text-on-surface-variant',
                   'focus:outline-none focus:ring-2 focus:ring-primary/40',
                   'border-error ring-1 ring-error/40' => $errors->has($name),
               ]) }}>

        <button type="button" x-show="value && !open" x-cloak @click="clear()"
                class="absolute top-1/2 -translate-y-1/2 end-2 p-1 rounded-lg
                       text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5"
                title="{{ __('common.clear') }}">
            <span class="material-symbols-outlined text-[16px]">close</span>
        </button>
        <span x-show="loading" x-cloak
              class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 end-3 text-[16px] animate-spin
                     text-on-surface-variant">progress_activity</span>
    </div>

    <ul x-show="open" x-cloak
        class="absolute z-50 mt-1 w-full max-h-64 overflow-y-auto rounded-xl shadow-lg py-1
               bg-surface dark:bg-[#1e2435] border border-outline-variant dark:border-white/10">
        <template x-for="(option, index) in results" :key="option.id">
            <li @mousedown.prevent="pick(option)"
                @mouseenter="active = index"
                :class="index === active ? 'bg-secondary/10 text-secondary' : 'text-on-surface dark:text-white'"
                class="px-3 py-2 text-sm cursor-pointer"
                x-text="option.label"></li>
        </template>
        <li x-show="!loading && results.length === 0"
            class="px-3 py-2 text-sm text-on-surface-variant dark:text-on-primary-container">
            {{ __('common.no_results') }}
        </li>
    </ul>

    @if ($hint)
        <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
