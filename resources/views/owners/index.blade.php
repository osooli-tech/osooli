@extends('layouts.app')

@section('title', __('owners.title'))
@section('page-title', __('owners.title'))

@section('breadcrumb')
    <span class="material-symbols-outlined text-[12px]">/</span>
    <span>{{ __('owners.title') }}</span>
@endsection

@section('content')
@can('parcels.view')
    <livewire:owners.owner-index />
@else
    <div class="flex flex-col items-center justify-center py-32 gap-4
                text-on-surface-variant dark:text-on-primary-container">
        <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
        <p class="text-sm">{{ __('permissions.unauthorized') }}</p>
    </div>
@endcan
@endsection
