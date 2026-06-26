@extends('layouts.app')

@section('title', __('documents.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('documents.title') }}</span>
@endsection

@section('page-title', __('documents.title'))

@section('content')
    @can('documents.download')
        <livewire:documents.document-index />
    @else
        <div class="flex flex-col items-center justify-center py-24 gap-4
                    text-on-surface-variant dark:text-on-primary-container">
            <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
            <p class="text-base">{{ __('permissions.unauthorized') }}</p>
        </div>
    @endcan
@endsection
