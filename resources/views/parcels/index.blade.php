@extends('layouts.app')

@section('title', __('parcels.title'))
@section('page-title', __('parcels.title'))

@section('content')
@can('parcels.view')
    <livewire:parcels.parcel-index />
@else
    <div class="flex flex-col items-center justify-center py-32 gap-4
                text-on-surface-variant dark:text-on-primary-container">
        <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
        <p class="text-sm">{{ __('permissions.unauthorized') }}</p>
    </div>
@endcan
@endsection
