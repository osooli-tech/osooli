@extends('layouts.app')

@section('title', __('parcels.title'))
@section('page-title', __('parcels.title'))

@section('content')
@can('parcels.view')
    @can('parcels.create')
        <div class="flex justify-end mb-4">
            {{-- Livewire.dispatch, not Alpine's $dispatch: this button sits in
                 the page wrapper, outside any component, so it needs the global
                 API to reach the modal's #[On('parcel-create')] listener.
                 Dispatching rather than navigating keeps the list's filters and
                 scroll position while a parcel is added. --}}
            <button type="button"
                    onclick="Livewire.dispatch('parcel-create')"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium
                           bg-primary text-white hover:bg-primary/90 transition">
                <span class="material-symbols-outlined text-[18px]">add</span>
                {{ __('parcels.create_title') }}
            </button>
        </div>
    @endcan

    <livewire:parcels.parcel-index />

    @canany(['parcels.create', 'parcels.edit'])
        <livewire:parcels.parcel-form-modal />
    @endcanany
@else
    <div class="flex flex-col items-center justify-center py-32 gap-4
                text-on-surface-variant dark:text-on-primary-container">
        <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
        <p class="text-sm">{{ __('permissions.unauthorized') }}</p>
    </div>
@endcan
@endsection
