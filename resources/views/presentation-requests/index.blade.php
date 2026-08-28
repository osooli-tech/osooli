@extends('layouts.app')

@section('title', __('presentation_requests.title'))
@section('page-title', __('presentation_requests.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span>{{ __('presentation_requests.title') }}</span>
@endsection

@section('content')
    <div class="mb-5">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
            {{ __('presentation_requests.subtitle') }}
        </p>
    </div>

    <livewire:presentation-requests.request-index />
@endsection
