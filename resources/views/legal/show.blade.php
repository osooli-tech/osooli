@extends('layouts.legal')

@section('title', $title)
@section('updated', $document->updated_at?->format('Y/m/d'))

@section('content')
    @unless ($isTranslated)
        {{-- Say so rather than silently serving a language the reader did not ask for. --}}
        <p class="mb-6 rounded-lg border-s-[3px] border-tertiary bg-tertiary-container/30 px-4 py-3 text-sm">
            {{ __('legal.no_translation') }}
        </p>
    @endunless

    <div class="legal-content space-y-4
                [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-8 [&_h2]:mb-3
                [&_h3]:text-lg [&_h3]:font-bold [&_h3]:mt-6 [&_h3]:mb-2
                [&_ul]:list-disc [&_ul]:ps-6 [&_ul]:space-y-2
                [&_ol]:list-decimal [&_ol]:ps-6 [&_ol]:space-y-2
                [&_a]:underline"
         @unless ($isTranslated) dir="rtl" @endunless>
        {{-- Purified on write by the model, never on render. --}}
        {!! $content !!}
    </div>
@endsection
