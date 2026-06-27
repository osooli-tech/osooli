@extends('layouts.app')

@section('title', __('modification_requests.title'))
@section('page-title', __('modification_requests.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span>{{ __('modification_requests.title') }}</span>
@endsection

@section('content')
    <div class="mb-5">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
            {{ __('modification_requests.subtitle') }}
        </p>
    </div>

    <livewire:modification-requests.request-index />
@endsection
