<svg viewBox="0 0 100 100" {{ $attributes->merge(['class' => 'shrink-0']) }} aria-hidden="true">
    @if ($path !== '')
        <path d="{{ $path }}" class="fill-secondary/20 stroke-secondary" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
    @else
        <rect x="20" y="20" width="60" height="60" rx="6" class="fill-none stroke-outline-variant" stroke-dasharray="6 5" stroke-width="2" />
    @endif
</svg>
