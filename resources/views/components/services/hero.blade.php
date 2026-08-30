@props(['icon', 'title', 'subtitle', 'badges' => []])

<div class="bg-primary rounded-2xl p-6 sm:p-8 mb-6 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 85% 20%, white 0%, transparent 45%);"></div>
    <div class="relative flex items-start gap-4">
        <div class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[28px] text-tertiary-container"
                  style="font-variation-settings: 'FILL' 1;">{{ $icon }}</span>
        </div>
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-white mb-1.5">{{ $title }}</h1>
            <p class="text-sm text-primary-fixed-dim max-w-2xl leading-relaxed">{{ $subtitle }}</p>
        </div>
    </div>

    @if ($badges !== [])
        <div class="relative flex flex-wrap gap-2.5 mt-5">
            @foreach ($badges as $badge)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium
                             bg-white/10 text-white">
                    <span class="material-symbols-outlined text-[15px]">{{ $badge['icon'] }}</span>
                    {{ $badge['label'] }}
                </span>
            @endforeach
        </div>
    @endif
</div>
