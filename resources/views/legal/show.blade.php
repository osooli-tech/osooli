@extends('layouts.legal')

@section('title', $title)
@section('updated', $document->updated_at?->format('Y/m/d'))

@section('content')
    @unless ($isTranslated)
        {{-- Say so rather than silently serving a language the reader did not ask for. --}}
        <p class="legal-notice">{{ __('legal.no_translation') }}</p>
    @endunless

    {{-- The document is written in the dashboard editor and purified by the
         model on write, never on render. When no translation exists for the
         visitor's language the Arabic original is served, so the direction is
         pinned back to RTL for that block alone. --}}
    <div class="legal-prose" @unless ($isTranslated) dir="rtl" @endunless>
        {!! $content !!}
    </div>
@endsection
