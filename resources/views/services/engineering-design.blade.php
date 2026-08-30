@extends('layouts.app')

@section('title', __('services.engineering_title'))
@section('page-title', __('services.engineering_title'))

@section('content')

    <x-services.hero
        icon="architecture"
        :title="__('services.engineering_title')"
        :subtitle="__('services.engineering_subtitle')"
        :badges="[
            ['icon' => 'payments', 'label' => __('services.badge_competitive_pricing')],
            ['icon' => 'event_available', 'label' => __('services.badge_on_schedule')],
            ['icon' => 'verified', 'label' => __('services.badge_high_quality')],
            ['icon' => 'groups', 'label' => __('services.badge_specialised_team')],
        ]"
    />

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ([
            ['icon' => 'architecture', 'key' => 'architectural'],
            ['icon' => 'foundation', 'key' => 'structural'],
            ['icon' => 'electric_bolt', 'key' => 'electrical'],
            ['icon' => 'hvac', 'key' => 'mechanical'],
            ['icon' => 'plumbing', 'key' => 'plumbing'],
            ['icon' => 'landscape', 'key' => 'site_coordination'],
            ['icon' => 'design_services', 'key' => 'executive_drawings'],
            ['icon' => 'view_in_ar', 'key' => 'bim'],
        ] as $item)
            <x-services.card
                :icon="$item['icon']"
                :title="__('services.engineering_'.$item['key'].'_title')"
                :description="__('services.engineering_'.$item['key'].'_description')"
                :service-name="__('services.engineering_'.$item['key'].'_title')"
            />
        @endforeach
    </div>

    <x-services.contact-cta />

@endsection
