@extends('layouts.app')

@section('title', __('map_layers.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('map_layers.title') }}</span>
@endsection

@section('page-title', __('map_layers.title'))

@section('content')
    <livewire:imports.map-layer-manager />
@endsection
