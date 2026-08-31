@extends('layouts.app')

@section('title', __('services.solar_title'))
@section('page-title', __('services.solar_title'))

@section('content')

    <x-services.hero
        icon="solar_power"
        :title="__('services.solar_title')"
        :subtitle="__('services.solar_subtitle')"
    />

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        @foreach ([
            ['icon' => 'design_services', 'key' => 'system_design'],
            ['icon' => 'query_stats', 'key' => 'feasibility_study'],
            ['icon' => 'construction', 'key' => 'installation'],
            ['icon' => 'build_circle', 'key' => 'maintenance'],
        ] as $item)
            <x-services.card
                :icon="$item['icon']"
                :title="__('services.solar_'.$item['key'].'_title')"
                :description="__('services.solar_'.$item['key'].'_description')"
                :service-name="__('services.solar_'.$item['key'].'_title')"
            />
        @endforeach
    </div>

    {{-- Why us --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 sm:p-6
                border border-outline-variant dark:border-white/10 shadow-sm mb-6">
        <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-5">
            {{ __('services.solar_why_heading') }}
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-5 text-center">
            @foreach ([
                ['icon' => 'savings', 'key' => 'cost'],
                ['icon' => 'verified_user', 'key' => 'warranty'],
                ['icon' => 'engineering', 'key' => 'technicians'],
                ['icon' => 'groups', 'key' => 'team'],
                ['icon' => 'tune', 'key' => 'custom'],
            ] as $item)
                <div>
                    <div class="w-11 h-11 rounded-xl bg-secondary/10 dark:bg-secondary/20 flex items-center
                                justify-center mx-auto mb-2">
                        <span class="material-symbols-outlined text-[20px] text-secondary"
                              style="font-variation-settings: 'FILL' 1;">{{ $item['icon'] }}</span>
                    </div>
                    <p class="text-xs font-medium text-on-surface dark:text-white">
                        {{ __('services.solar_why_'.$item['key']) }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Process — a real sequence, so numbering carries information here. --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 sm:p-6
                border border-outline-variant dark:border-white/10 shadow-sm mb-6">
        <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-5">
            {{ __('services.solar_steps_heading') }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-5 gap-4">
            @foreach (['contact', 'site_visit', 'design', 'installation', 'maintenance'] as $i => $key)
                <div class="flex sm:flex-col items-center sm:text-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-secondary text-white text-xs font-bold
                                 flex items-center justify-center shrink-0 data-tabular">
                        {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                    </span>
                    <p class="text-xs font-medium text-on-surface dark:text-white">
                        {{ __('services.solar_step_'.$key) }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- CTA --}}
    <div class="bg-secondary/10 dark:bg-secondary/15 rounded-2xl p-6 text-center mb-6">
        <p class="font-semibold text-on-surface dark:text-white mb-3">{{ __('services.solar_cta_heading') }}</p>
        <a href="https://wa.me/{{ preg_replace('/\D/', '', config('landing.contact_whatsapp')) }}?text={{ rawurlencode(__('services.solar_cta_subject')) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-medium
                  bg-secondary text-white hover:opacity-90 transition-opacity">
            <span class="material-symbols-outlined text-[16px]">chat</span>
            {{ __('services.solar_cta_button') }}
        </a>
    </div>

    <x-services.contact-cta />

@endsection
