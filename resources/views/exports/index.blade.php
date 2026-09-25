@extends('layouts.app')

@section('title', __('exports_center.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('exports_center.title') }}</span>
@endsection

@section('page-title', __('exports_center.title'))

@section('content')
    <livewire:exports.export-center />
@endsection
