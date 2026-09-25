@extends('layouts.app')

@section('title', __('placement.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <a href="{{ route('parcels.index') }}" class="text-on-surface-variant dark:text-on-primary-container hover:text-secondary">{{ __('nav.parcels') }}</a>
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('placement.title') }}</span>
@endsection

@section('page-title', __('placement.title'))

@section('content')
    <livewire:parcels.placement-review />
@endsection
