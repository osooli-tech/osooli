@extends('layouts.app')

@section('title', __('imports_center.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('imports_center.title') }}</span>
@endsection

@section('page-title', __('imports_center.title'))

@section('content')
    <livewire:imports.import-center />
@endsection
