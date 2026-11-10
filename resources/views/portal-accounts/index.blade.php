@extends('layouts.app')

@section('title', __('portal_accounts.title'))
@section('page-title', __('portal_accounts.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span>{{ __('portal_accounts.title') }}</span>
@endsection

@section('content')
    <livewire:portal-accounts.account-index />
@endsection
