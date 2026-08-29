@props([
    'quote',
    'role',
    'org',
    'icon' => 'building',   // building | shield | list
    'featured' => false,
])

{{-- A customer quote. Attribution is by role and organisation, not by name. --}}
<figure class="quote @if ($featured) quote--hi @endif">
    <svg class="qm" viewBox="0 0 32 24" fill="currentColor" aria-hidden="true" focusable="false">
        <path d="M13 24V12C13 5.4 8.4.8 1.6 0L0 3.6C4 4.6 6.4 7.2 6.6 11H1v13zM32 24V12c0-6.6-4.6-11.2-11.4-12L19 3.6c4 1 6.4 3.6 6.6 7.4H20v13z"/>
    </svg>

    <blockquote><p>{{ $quote }}</p></blockquote>

    <figcaption class="who">
        <i>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                @switch($icon)
                    @case('shield')
                        <path d="M12 1 3 5v6c0 5.6 3.8 10.7 9 12 5.2-1.3 9-6.4 9-12V5z"/>
                        @break
                    @case('list')
                        <path d="M4 4h16v4H4zm0 6h16v4H4zm0 6h10v4H4z"/>
                        @break
                    @default
                        <path d="M12 2 3 8.4V22h6.2v-6.4h5.6V22H21V8.4z"/>
                @endswitch
            </svg>
        </i>
        <div>
            <b>{{ $role }}</b>
            <span>{{ $org }}</span>
        </div>
    </figcaption>
</figure>
