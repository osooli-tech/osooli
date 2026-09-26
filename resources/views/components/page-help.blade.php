{{-- "What is this page?" — a short explanation above each page's content,
     from lang/*/help.php by route name. Pages with no entry show nothing.
     Folding it away is remembered per page, in this browser only. --}}
@php
    $route = request()->route()?->getName();
    // Route names hold dots, which lang keys read as nesting: parcels.show
    // is kept as parcels_show.
    $key = $route === null ? null : 'help.pages.'.str_replace('.', '_', $route);
    $help = $key !== null && \Illuminate\Support\Facades\Lang::has($key) ? __($key) : null;
@endphp

@if (is_array($help))
    <div x-data="{
            key: @js('page-help:'.$route),
            open: true,
            init() { try { this.open = localStorage.getItem(this.key) !== 'closed'; } catch (e) {} },
            toggle() { this.open = ! this.open; try { localStorage.setItem(this.key, this.open ? 'open' : 'closed'); } catch (e) {} },
         }"
         class="mb-4 rounded-2xl border border-primary/15 dark:border-white/10 bg-primary/5 dark:bg-white/5">
        <button type="button" x-on:click="toggle()"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-start text-sm font-semibold text-primary dark:text-white">
            <span class="material-symbols-outlined text-[20px]">help</span>
            <span class="flex-1">{{ __('help.what_is_this') }}</span>
            <span class="material-symbols-outlined text-[20px] transition-transform" :class="open && 'rotate-180'">expand_more</span>
        </button>

        <div x-show="open" x-cloak class="space-y-2 px-4 pb-4 text-sm leading-relaxed text-on-surface dark:text-white/90">
            <p>{{ $help['what'] }}</p>

            @if (! empty($help['steps']))
                <ul class="list-disc space-y-1 ps-5">
                    @foreach ($help['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ul>
            @endif

            @if (! empty($help['note']))
                <p class="flex items-start gap-1.5 rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-amber-800 dark:text-amber-200">
                    <span class="material-symbols-outlined text-[18px] shrink-0">lightbulb</span>
                    <span>{{ $help['note'] }}</span>
                </p>
            @endif
        </div>
    </div>
@endif
