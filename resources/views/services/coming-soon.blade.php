@extends('layouts.app')

@section('title', $title)
@section('page-title', $title)

@section('content')

    <div class="max-w-xl mx-auto text-center py-10">
        <div class="w-16 h-16 rounded-2xl bg-secondary/10 dark:bg-secondary/20 flex items-center justify-center mx-auto mb-5">
            <span class="material-symbols-outlined text-[32px] text-secondary"
                  style="font-variation-settings: 'FILL' 1;">{{ $icon }}</span>
        </div>

        <p class="text-3xl font-bold text-secondary mb-3">{{ __('services.coming_soon_badge') }}</p>
        <p class="text-on-surface-variant dark:text-on-primary-container leading-relaxed mb-8">
            {{ $description }}
        </p>

        {{-- Real action, not a fake subscribe form: opens the visitor's mail
             client addressed to us, naming the page they were on. Nothing is
             silently "saved" that isn't actually captured anywhere yet. --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm inline-flex items-center gap-4">
            <span class="material-symbols-outlined text-[22px] text-tertiary shrink-0">notifications_active</span>
            <div class="text-start">
                <p class="text-sm font-medium text-on-surface dark:text-white">{{ __('services.notify_heading') }}</p>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('services.notify_subtitle') }}</p>
            </div>
            <a href="mailto:{{ config('landing.contact_email') }}?subject={{ rawurlencode(__('services.notify_subject', ['service' => $title])) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-medium shrink-0
                      bg-secondary text-white hover:opacity-90 transition-opacity">
                <span class="material-symbols-outlined text-[15px]">notifications</span>
                {{ __('services.notify_button') }}
            </a>
        </div>
    </div>

    <x-services.contact-cta />

@endsection
