@extends('layouts.app')

@section('title', __('services.municipal_title'))
@section('page-title', __('services.municipal_title'))

@section('content')

    <x-services.hero
        icon="account_balance"
        :title="__('services.municipal_title')"
        :subtitle="__('services.municipal_subtitle')"
        :badges="[
            ['icon' => 'schedule', 'label' => __('services.municipal_badge_time_saving')],
            ['icon' => 'my_location', 'label' => __('services.municipal_badge_tracking')],
            ['icon' => 'verified_user', 'label' => __('services.municipal_badge_secure_payment')],
            ['icon' => 'notifications_active', 'label' => __('services.municipal_badge_notifications')],
        ]"
    />

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        @foreach ([
            ['icon' => 'apartment', 'key' => 'building_permits'],
            ['icon' => 'storefront', 'key' => 'shop_licenses'],
            ['icon' => 'workspace_premium', 'key' => 'certificates'],
            ['icon' => 'edit_document', 'key' => 'amend_license'],
            ['icon' => 'domain_disabled', 'key' => 'demolition'],
            ['icon' => 'plumbing', 'key' => 'utilities'],
            ['icon' => 'ad_units', 'key' => 'billboards'],
            ['icon' => 'business_center', 'key' => 'commercial_activity'],
            ['icon' => 'fence', 'key' => 'fencing'],
            ['icon' => 'more_horiz', 'key' => 'other'],
        ] as $item)
            <x-services.card
                :icon="$item['icon']"
                :title="__('services.municipal_'.$item['key'].'_title')"
                :description="__('services.municipal_'.$item['key'].'_description')"
                :service-name="__('services.municipal_'.$item['key'].'_title')"
            />
        @endforeach
    </div>

    {{-- About --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 sm:p-6
                border border-outline-variant dark:border-white/10 shadow-sm mb-6">
        <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-3">
            {{ __('services.municipal_about_heading') }}
        </h2>
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container leading-relaxed">
            {{ __('services.municipal_about_body') }}
        </p>
    </div>

    {{-- Features --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 sm:p-6
                border border-outline-variant dark:border-white/10 shadow-sm mb-6">
        <h2 class="font-semibold text-on-surface dark:text-white text-sm mb-4">
            {{ __('services.municipal_features_heading') }}
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach (['available', 'tracking', 'payment', 'notifications'] as $key)
                <div class="flex items-center gap-2.5">
                    <span class="material-symbols-outlined text-[18px] text-secondary shrink-0">check_circle</span>
                    <p class="text-sm text-on-surface dark:text-white">
                        {{ __('services.municipal_feature_'.$key) }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- CTA --}}
    <div class="bg-secondary/10 dark:bg-secondary/15 rounded-2xl p-6 text-center mb-6">
        <a href="https://wa.me/{{ preg_replace('/\D/', '', config('landing.contact_whatsapp')) }}?text={{ rawurlencode(__('services.municipal_cta_subject', ['service' => __('services.municipal_title')])) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-medium
                  bg-secondary text-white hover:opacity-90 transition-opacity">
            <span class="material-symbols-outlined text-[16px]">chat</span>
            {{ __('services.municipal_cta_button') }}
        </a>
    </div>

    <x-services.contact-cta />

@endsection
