@extends('layouts.app')

@section('title', __('services.survey_title'))
@section('page-title', __('services.survey_title'))

@section('content')

    <x-services.hero
        icon="straighten"
        :title="__('services.survey_title')"
        :subtitle="__('services.survey_subtitle')"
    />

    <h2 class="text-sm font-semibold text-on-surface dark:text-white mb-3">
        {{ __('services.survey_methods_heading') }}
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        @foreach ([
            ['icon' => 'flight', 'key' => 'drone'],
            ['icon' => 'gps_fixed', 'key' => 'gps_rtk'],
            ['icon' => '3d_rotation', 'key' => 'lidar'],
            ['icon' => 'radar', 'key' => 'gpr'],
            ['icon' => 'square_foot', 'key' => 'boundary'],
            ['icon' => 'terrain', 'key' => 'topographic'],
        ] as $item)
            <x-services.card
                :icon="$item['icon']"
                :title="__('services.survey_'.$item['key'].'_title')"
                :description="__('services.survey_'.$item['key'].'_description')"
                :service-name="__('services.survey_'.$item['key'].'_title')"
            />
        @endforeach
    </div>

    <h2 class="text-sm font-semibold text-on-surface dark:text-white mb-3">
        {{ __('services.survey_reports_heading') }}
    </h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach ([
            ['icon' => 'summarize', 'key' => 'land_status'],
            ['icon' => 'fact_check', 'key' => 'survey_report'],
            ['icon' => 'border_style', 'key' => 'boundary_determination'],
            ['icon' => 'grid_view', 'key' => 'subdivision'],
            ['icon' => 'rule', 'key' => 'deed_matching'],
            ['icon' => 'draw', 'key' => 'sketch'],
            ['icon' => 'height', 'key' => 'levels'],
            ['icon' => 'cable', 'key' => 'utilities'],
        ] as $item)
            <x-services.card
                :icon="$item['icon']"
                :title="__('services.survey_'.$item['key'].'_title')"
                :description="__('services.survey_'.$item['key'].'_description')"
                :service-name="__('services.survey_'.$item['key'].'_title')"
            />
        @endforeach
    </div>

    <x-services.contact-cta />

@endsection
