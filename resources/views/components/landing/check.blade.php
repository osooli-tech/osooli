{{-- A ticked line in a feature list. Usage: <x-landing.check>text</x-landing.check> --}}
<li>
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
        <path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
    </svg>
    {{ $slot }}
</li>
