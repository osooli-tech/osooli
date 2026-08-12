@extends('layouts.legal')

@section('title', $document->title)
@section('updated', $document->updated_at?->format('Y/m/d'))

@section('content')
    <div class="legal-content space-y-4
                [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-8 [&_h2]:mb-3
                [&_h3]:text-lg [&_h3]:font-bold [&_h3]:mt-6 [&_h3]:mb-2
                [&_ul]:list-disc [&_ul]:pr-6 [&_ul]:space-y-2
                [&_ol]:list-decimal [&_ol]:pr-6 [&_ol]:space-y-2
                [&_a]:underline">
        {!! $document->content !!}
    </div>
@endsection
