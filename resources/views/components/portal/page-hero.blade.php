@props(['icon' => null, 'title', 'subtitle' => null])

{{-- The portal's signature band: navy plat sheet with a faint parcel grid and
     two soft glows — the same visual language as the public landing page. --}}
<section {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-3xl text-white p-6 lg:p-8 bg-gradient-to-br from-primary via-primary-container to-on-secondary-fixed-variant']) }}>
    <svg class="absolute inset-0 w-full h-full opacity-[0.09] pointer-events-none" aria-hidden="true">
        <defs>
            <pattern id="plat-{{ $attributes->get('id', 'hero') }}" width="64" height="48" patternUnits="userSpaceOnUse">
                <path d="M64 0H0V48" fill="none" stroke="white" stroke-width="1" />
                <path d="M32 0V48" fill="none" stroke="white" stroke-width=".5" stroke-dasharray="2 4" />
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#plat-{{ $attributes->get('id', 'hero') }})" />
    </svg>
    <div class="absolute -top-24 -end-16 w-72 h-72 rounded-full bg-tertiary-container/25 blur-3xl pointer-events-none" aria-hidden="true"></div>
    <div class="absolute -bottom-28 -start-10 w-80 h-80 rounded-full bg-secondary-fixed-dim/20 blur-3xl pointer-events-none" aria-hidden="true"></div>

    <div class="relative">
        @if ($icon || $title)
            <div class="flex items-center gap-3">
                @if ($icon)
                    <span class="w-11 h-11 rounded-2xl bg-white/10 ring-1 ring-white/15 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[24px] text-tertiary-fixed-dim">{{ $icon }}</span>
                    </span>
                @endif
                <div class="min-w-0">
                    <h1 class="text-xl lg:text-2xl font-bold truncate">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="text-sm text-primary-fixed-dim">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
        @endif

        {{ $slot }}
    </div>
</section>
