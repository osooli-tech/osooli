@extends('layouts.landing')

@section('content')

    {{-- The bands alternate navy and light down the page; each section owns its
         own background so the rhythm reads from this file alone. --}}
    @include('landing.sections.hero', ['stats' => config('landing.stats')])
    @include('landing.sections.features-light')
    @include('landing.sections.decisions')
    @include('landing.sections.compare')
    @include('landing.sections.pricing')
    @include('landing.sections.app')
    @include('landing.sections.trust')
    @include('landing.sections.stack')
    @include('landing.sections.testimonials')
    @include('landing.sections.cta')

@endsection
