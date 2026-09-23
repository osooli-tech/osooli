@extends('layouts.app')

@section('title', __('database_settings.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <a href="{{ route('settings.index') }}" class="text-on-surface-variant dark:text-on-primary-container hover:text-secondary">{{ __('settings.title') }}</a>
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('database_settings.title') }}</span>
@endsection

@section('page-title', __('database_settings.title'))

@section('content')
    <livewire:settings.database-settings-manager />
@endsection
