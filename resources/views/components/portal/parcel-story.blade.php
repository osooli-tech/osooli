@props(['events'])

{{-- Newest first, on a gradient spine. Each event keeps its Hijri date when
     it came from a deed, alongside the Gregorian one. --}}
@php
    $tones = [
        'secondary' => 'from-secondary to-secondary-fixed-dim',
        'primary' => 'from-primary to-surface-tint',
        'tertiary' => 'from-tertiary to-tertiary-container',
        'outline' => 'from-outline to-outline-variant',
    ];
@endphp
@if (empty($events))
    <p class="text-sm text-on-surface-variant dark:text-on-primary-container py-6 text-center">{{ __('portal.story_empty') }}</p>
@else
    <ol class="relative ms-4">
        <span class="absolute top-2 bottom-2 start-0 w-0.5 bg-gradient-to-b from-tertiary-container via-secondary to-transparent" aria-hidden="true"></span>
        @foreach ($events as $e)
            <li class="relative ps-8 pb-6 last:pb-0">
                <span class="absolute start-0 top-0 -translate-x-1/2 rtl:translate-x-1/2 w-9 h-9 rounded-full flex items-center justify-center text-white shadow-md ring-4 ring-surface-container-lowest dark:ring-[#141b29] bg-gradient-to-br {{ $tones[$e['tone']] ?? $tones['primary'] }}">
                    <span class="material-symbols-outlined text-[18px]">{{ $e['icon'] }}</span>
                </span>
                <div class="rounded-2xl p-3 bg-surface-container-low dark:bg-white/5 hover:bg-surface-container dark:hover:bg-white/10 transition-colors">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold">{{ $e['title'] }}</p>
                        <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container data-tabular">
                            @if ($e['hijri']){{ $e['hijri'] }}{{ __('portal.hijri_suffix') }} · @endif
                            <span dir="ltr">{{ date('Y-m-d', $e['at']) }}</span>
                        </p>
                    </div>
                    @if ($e['detail'])
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5 truncate" dir="auto">{{ $e['detail'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
