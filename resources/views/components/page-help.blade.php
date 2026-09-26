{{-- "What is this page?" — a small "?" beside the page title that opens a
     short explanation, from lang/*/help.php by route name. Closed until
     asked for, closed again by a click elsewhere or Escape. Pages with no
     entry show nothing. --}}
@php
    $route = request()->route()?->getName();
    // Route names hold dots, which lang keys read as nesting: parcels.show
    // is kept as parcels_show.
    $key = $route === null ? null : 'help.pages.'.str_replace('.', '_', $route);
    $help = $key !== null && \Illuminate\Support\Facades\Lang::has($key) ? __($key) : null;
@endphp

@if (is_array($help) && auth()->user()?->can('help.view'))
    <div x-data="{ open: false }" x-on:keydown.escape.window="open = false" class="relative shrink-0">
        <button type="button" x-on:click="open = ! open"
                title="{{ __('help.what_is_this') }}" aria-label="{{ __('help.what_is_this') }}"
                :aria-expanded="open"
                class="flex items-center justify-center w-6 h-6 rounded-full text-on-surface-variant/70 dark:text-on-primary-container/70
                       hover:text-primary hover:bg-primary/10 dark:hover:text-white dark:hover:bg-white/10 transition-colors"
                :class="open && 'text-primary bg-primary/10 dark:text-white dark:bg-white/10'">
            <span class="material-symbols-outlined text-[18px]">help</span>
        </button>

        <div x-show="open" x-cloak x-transition.opacity.duration.100ms x-on:click.outside="open = false"
             class="absolute top-full start-0 z-50 mt-2 w-[360px] max-w-[85vw] rounded-xl border border-outline-variant dark:border-white/10
                    bg-surface-container-lowest dark:bg-[#1f2536] p-4 shadow-xl space-y-2
                    text-sm font-normal leading-relaxed text-on-surface dark:text-white/90 whitespace-normal">
            <p class="font-semibold text-primary dark:text-white">{{ __('help.what_is_this') }}</p>
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
