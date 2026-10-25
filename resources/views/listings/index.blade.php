@extends('layouts.app')

@section('title', __('listings.staff_title'))
@section('page-title', __('listings.staff_title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span>{{ __('listings.staff_title') }}</span>
@endsection

@section('content')
    <div class="mb-5">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('listings.staff_subtitle') }}</p>
    </div>

    <livewire:listings.listing-index />
@endsection
