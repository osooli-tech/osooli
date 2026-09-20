@extends('layouts.app')

@section('title', __('survey_decisions.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('survey_decisions.title') }}</span>
@endsection

@section('page-title', __('survey_decisions.title'))

@section('content')
    @can('parcels.view')
        <livewire:survey-decisions.survey-decision-index />

        {{-- Opened by Livewire.dispatch('open-survey-decision', { parcelId, decisionId })
             from the list rows below. Mounted once here so a single modal instance
             serves every row. --}}
        @can('survey_decisions.edit')
            <livewire:survey-decisions.survey-decision-form-modal />
        @endcan
    @else
        <div class="flex flex-col items-center justify-center py-24 gap-4
                    text-on-surface-variant dark:text-on-primary-container">
            <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
            <p class="text-base">{{ __('permissions.unauthorized') }}</p>
        </div>
    @endcan
@endsection
