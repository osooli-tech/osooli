@extends('layouts.app')

@section('title', __('settings.title'))

@section('breadcrumb')
    <span class="text-on-surface-variant dark:text-on-primary-container mx-1">/</span>
    <span class="text-on-surface dark:text-white">{{ __('settings.title') }}</span>
@endsection

@section('page-title', __('settings.title'))

@section('content')
    @can('roles.manage')
        {{-- Legal content editors --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            @foreach (\App\Models\LegalDocument::whereIn('key', \App\Models\LegalDocument::KEYS)->get() as $legalDoc)
                <a href="{{ route('legal.edit', $legalDoc->key) }}"
                   class="flex items-center gap-4 bg-white dark:bg-[#0b1a2b] rounded-2xl shadow-sm p-5
                          hover:ring-2 hover:ring-secondary transition">
                    <span class="material-symbols-outlined text-secondary text-[32px]">
                        {{ $legalDoc->key === 'privacy' ? 'privacy_tip' : 'gavel' }}
                    </span>
                    <span>
                        <span class="block font-semibold text-on-surface dark:text-white">{{ $legalDoc->title }}</span>
                        <span class="block text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5">
                            {{ __('آخر تحديث') }}: {{ $legalDoc->updated_at?->format('Y/m/d') }}
                        </span>
                    </span>
                </a>
            @endforeach
        </div>

        <livewire:settings.role-manager />
    @else
        <div class="flex flex-col items-center justify-center py-24 gap-4
                    text-on-surface-variant dark:text-on-primary-container">
            <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
            <p class="text-base">{{ __('permissions.unauthorized') }}</p>
        </div>
    @endcan
@endsection
