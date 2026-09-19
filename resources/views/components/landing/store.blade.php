{{--
    An app store badge. A store still on the "#" placeholder has no listing
    yet, so its badge says "coming soon" and is not a link — a badge that goes
    nowhere reads as broken.
--}}
@props(['name', 'url', 'icon'])

@php
    $live = filled($url) && $url !== '#';
    $tag = $live ? 'a' : 'span';
@endphp

<{{ $tag }} {{ $attributes->class(['store', 'store--soon' => ! $live]) }}
    @if ($live) href="{{ $url }}" rel="noopener" @endif>
    @if ($icon === 'apple')
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M17 12.5c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.5-.2-2.8.8-3.6.8s-1.9-.8-3.1-.8c-1.6 0-3 .9-3.8 2.4-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3.1-.7s1.8.7 3.1.7c1.3 0 2.1-1.1 2.9-2.3.9-1.3 1.3-2.6 1.3-2.7-.1 0-2.5-1-2.6-3.7M14.8 5.2c.6-.8 1.1-1.9 1-3-.9 0-2.1.6-2.8 1.4-.6.7-1.2 1.8-1 2.9 1 .1 2.1-.5 2.8-1.3"/>
        </svg>
    @else
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M3.6 1.8 14 12 3.6 22.2c-.4-.3-.6-.8-.6-1.4V3.2c0-.6.2-1.1.6-1.4m11.9 7.4 2.8 2.8-2.8 2.8L12.7 12zM4.9 1.2l10.1 5.8-2.5 2.5zm0 21.6 7.6-8.3 2.5 2.5z"/>
        </svg>
    @endif
    <u><small>{{ $live ? __('landing.store_from') : __('landing.store_soon') }}</small><b>{{ $name }}</b></u>
</{{ $tag }}>
