@extends('layouts.landing')

@section('content')

    {{-- The bands alternate navy and light down the page; each section owns its
         own background so the rhythm reads from this file alone. --}}
    @include('landing.sections.hero', ['stats' => config('landing.stats')])
    @include('landing.sections.features-light')
    @include('landing.sections.decisions')
    @include('landing.sections.compare')
    {{-- Pricing is hidden for now. The section, its tier component and its
         strings are all still in place: uncomment this line and the two
         "#pricing" links in the header and the footer to restore it. --}}
    {{-- @include('landing.sections.pricing') --}}
    @include('landing.sections.app')
    @include('landing.sections.trust')
    @include('landing.sections.stack')

    {{-- Testimonials are held back until we have quotes from real customers to
         run. The section, its component and its strings are all still in place:
         uncomment this line and the "#clients" link in the header to restore it. --}}
    {{-- @include('landing.sections.testimonials') --}}

    @include('landing.sections.cta')

@endsection
