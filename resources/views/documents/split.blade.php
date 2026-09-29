@extends('layouts.app')

@section('title', __('documents.split.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <a href="{{ route('documents.index') }}" class="text-on-surface-variant dark:text-on-primary-container hover:text-secondary">{{ __('nav.documents') }}</a>
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('documents.split.title') }}</span>
@endsection

@section('page-title', __('documents.split.title'))

@section('content')
    <livewire:documents.split-upload />
@endsection
