@extends('portal.layout')

@section('title', __('portal.nav_parcels'))

@section('content')
<div class="space-y-5">
    <x-portal.page-hero icon="map" :title="__('portal.nav_parcels')" :subtitle="__('portal.parcels_hero_subtitle')" id="parcels">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6">
            <x-portal.hero-stat icon="map" :label="__('dashboard.total_parcels')" :value="$summary['parcels_total']" />
            <x-portal.hero-stat icon="square_foot" :label="__('dashboard.total_area')" :value="$summary['area_total_sqm']" :suffix="__('dashboard.area_unit_sqm')" />
            <x-portal.hero-stat icon="verified" :label="__('portal.kpi_active_deeds')" :value="$summary['deeds_active']" />
            <x-portal.hero-stat icon="payments" :label="__('dashboard.total_estimated_value')" :value="$portfolio['total_value'] ?? '—'" :suffix="$portfolio['total_value'] !== null ? __('parcels.currency') : ''" />
        </div>
    </x-portal.page-hero>

    <livewire:portal.parcel-index />
</div>
@endsection
