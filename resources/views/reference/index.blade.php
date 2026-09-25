@extends('layouts.app')

@section('title', __('reference.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('reference.title') }}</span>
@endsection

@section('page-title', __('reference.title'))

@section('content')
    @can('reference.view')
        <livewire:reference.reference-index />
        @can('reference.edit')
            <livewire:reference.boundary-editor />
        @endcan
    @else
        <div class="flex flex-col items-center justify-center py-24 gap-4
                    text-on-surface-variant dark:text-on-primary-container">
            <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
            <p class="text-base">{{ __('permissions.unauthorized') }}</p>
        </div>
    @endcan
@endsection
