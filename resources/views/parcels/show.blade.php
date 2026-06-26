@extends('layouts.app')

@section('title', __('parcels.parcel_no').' '.$parcel->parcel_no)
@section('page-title', __('parcels.parcel_no').' '.$parcel->parcel_no)

@section('content')
@can('parcels.view')

{{-- Back + header -------------------------------------------------------- --}}
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('parcels.index') }}"
       class="flex items-center gap-1.5 text-sm text-on-surface-variant dark:text-on-primary-container
              hover:text-primary transition-colors">
        <span class="material-symbols-outlined text-[18px]">arrow_forward_ios</span>
        {{ __('parcels.back') }}
    </a>
    <div class="flex items-center gap-2 flex-wrap">
        @if ($parcel->asset_type)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                         bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90">
                {{ __('parcels.asset_types.'.$parcel->asset_type) }}
            </span>
        @endif
        @if ($parcel->land_transaction)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                         bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90">
                {{ __('parcels.land_transactions.'.$parcel->land_transaction) }}
            </span>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

    {{-- ── Main column (deeds + survey decisions + photos) ───────────── --}}
    <div class="xl:col-span-2 space-y-5">

        {{-- Deeds --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                    border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-4
                        border-b border-outline-variant dark:border-white/10">
                <span class="material-symbols-outlined text-[18px] text-secondary"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    description
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.deeds_section') }}
                </h2>
                <span class="ms-auto text-xs text-on-surface-variant dark:text-on-primary-container">
                    {{ $parcel->deeds->count() }}
                </span>
            </div>

            @if ($parcel->deeds->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 gap-3
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[40px] opacity-30">description</span>
                    <p class="text-sm">{{ __('parcels.no_deeds') }}</p>
                </div>
            @else
                <div class="divide-y divide-outline-variant dark:divide-white/10">
                    @foreach ($parcel->deeds as $deed)
                        @php
                            $isUpdated = $deed->deed_status === \App\Enums\DeedStatus::Updated->value;
                        @endphp
                        <div class="p-5">
                            {{-- Deed header row --}}
                            <div class="flex flex-wrap items-center gap-3 mb-4">
                                <span class="font-bold text-on-surface dark:text-white data-tabular text-base">
                                    {{ $deed->deed_no ?? '—' }}
                                </span>
                                @if ($deed->deed_status)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                 {{ $isUpdated
                                                     ? 'bg-secondary/10 text-secondary'
                                                     : 'bg-error/10 text-error' }}">
                                        {{ __('parcels.deed_statuses.'.$deed->deed_status) }}
                                    </span>
                                @endif
                                @if ($deed->deed_class)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                 bg-tertiary-container/30 text-on-surface dark:text-white/80">
                                        {{ $deed->deed_class }}
                                    </span>
                                @endif
                            </div>

                            {{-- Deed meta --}}
                            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm mb-4">
                                <div>
                                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">
                                        {{ __('parcels.deed_date') }}
                                    </dt>
                                    <dd class="font-medium text-on-surface dark:text-white data-tabular">
                                        {{ $deed->deed_date_hijri ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">
                                        {{ __('parcels.area_deed') }}
                                    </dt>
                                    <dd class="font-medium text-on-surface dark:text-white data-tabular">
                                        @if ($deed->deed_area)
                                            {{ number_format((float) $deed->deed_area, 0) }}
                                            {{ __('dashboard.area_unit_sqm') }}
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container mb-0.5">
                                        {{ __('parcels.deed_class') }}
                                    </dt>
                                    <dd class="font-medium text-on-surface dark:text-white">
                                        {{ $deed->deed_class ?? '—' }}
                                    </dd>
                                </div>
                            </dl>

                            {{-- Owners --}}
                            @if ($deed->owners->isNotEmpty())
                                <div class="bg-surface-container dark:bg-white/5 rounded-xl p-3">
                                    <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container mb-2">
                                        {{ __('parcels.owners') }}
                                    </p>
                                    <div class="space-y-2">
                                        @foreach ($deed->owners as $owner)
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant"
                                                          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                                                        person
                                                    </span>
                                                    <span class="text-on-surface dark:text-white font-medium">
                                                        {{ $owner->name }}
                                                    </span>
                                                    @if ($owner->national_id)
                                                        <span class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular">
                                                            {{ $owner->national_id }}
                                                        </span>
                                                    @endif
                                                </div>
                                                @if ($owner->pivot->ownership_share)
                                                    <span class="text-xs font-medium text-secondary shrink-0 data-tabular">
                                                        {{ $owner->pivot->ownership_share }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Survey Decisions --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                    border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-5 py-4
                        border-b border-outline-variant dark:border-white/10">
                <span class="material-symbols-outlined text-[18px] text-tertiary-container"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    gavel
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.survey_decisions_section') }}
                </h2>
                <span class="ms-auto text-xs text-on-surface-variant dark:text-on-primary-container">
                    {{ $parcel->surveyDecisions->count() }}
                </span>
            </div>

            @if ($parcel->surveyDecisions->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 gap-3
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[36px] opacity-30">gavel</span>
                    <p class="text-sm">{{ __('parcels.no_decisions') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-outline-variant dark:border-white/10
                                        bg-surface-container dark:bg-[#1e2435]">
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.folder') }}
                                </th>
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.report_no') }}
                                </th>
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.qrar_no') }}
                                </th>
                                <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.qrar_source') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                            @foreach ($parcel->surveyDecisions as $decision)
                                <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                        {{ $decision->folder ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                        {{ $decision->report_no ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-on-surface dark:text-white data-tabular">
                                        {{ $decision->qrar_no ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                        {{ $decision->qrar_source ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>

    {{-- ── Side column (parcel info + boundary) ───────────────────────── --}}
    <div class="space-y-5">

        {{-- Parcel info --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-primary"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    terrain
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.parcel_info') }}
                </h2>
            </div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.parcel_no') }}
                    </dt>
                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end">
                        {{ $parcel->parcel_no ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.geo_id') }}
                    </dt>
                    <dd class="font-medium text-on-surface dark:text-white data-tabular text-end text-xs break-all">
                        {{ $parcel->geo_id ?? '—' }}
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                        {{ __('parcels.plan_no') }}
                    </dt>
                    <dd class="font-semibold text-on-surface dark:text-white data-tabular text-end">
                        {{ $parcel->plan?->plan_no ?? '—' }}
                    </dd>
                </div>
                @if ($parcel->plan?->district)
                    <div class="flex justify-between gap-2">
                        <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">
                            {{ __('parcels.district') }}
                        </dt>
                        <dd class="font-medium text-on-surface dark:text-white text-end">
                            {{ app()->isLocale('ar') ? $parcel->plan->district->name_ar : $parcel->plan->district->name_en }}
                        </dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Boundary compass --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-[18px] text-secondary"
                      style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                    straighten
                </span>
                <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                    {{ __('parcels.boundary_section') }}
                </h2>
            </div>

            @if (! $parcel->boundary)
                <div class="flex flex-col items-center justify-center py-8 gap-3
                            text-on-surface-variant dark:text-on-primary-container">
                    <span class="material-symbols-outlined text-[36px] opacity-30">straighten</span>
                    <p class="text-sm">{{ __('parcels.no_boundary') }}</p>
                </div>
            @else
                @php $b = $parcel->boundary; @endphp

                {{-- Compass layout --}}
                <div class="grid grid-cols-3 gap-2 text-center text-xs mb-4">

                    {{-- Top row: north --}}
                    <div></div>
                    <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                        <p class="text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('parcels.n_border') }}
                        </p>
                        <p class="font-semibold text-on-surface dark:text-white leading-snug">
                            {{ $b->n_border ?? '—' }}
                        </p>
                        @if ($b->n_dim)
                            <p class="text-secondary data-tabular mt-0.5">{{ $b->n_dim }} م</p>
                        @endif
                    </div>
                    <div></div>

                    {{-- Middle row: west | center | east --}}
                    <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                        <p class="text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('parcels.w_border') }}
                        </p>
                        <p class="font-semibold text-on-surface dark:text-white leading-snug">
                            {{ $b->w_border ?? '—' }}
                        </p>
                        @if ($b->w_dim)
                            <p class="text-secondary data-tabular mt-0.5">{{ $b->w_dim }} م</p>
                        @endif
                    </div>
                    <div class="flex items-center justify-center">
                        <span class="material-symbols-outlined text-[32px] text-outline-variant dark:text-white/20"
                              style="font-variation-settings: 'FILL' 1, 'wght' 300, 'GRAD' 0, 'opsz' 24;">
                            explore
                        </span>
                    </div>
                    <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                        <p class="text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('parcels.e_border') }}
                        </p>
                        <p class="font-semibold text-on-surface dark:text-white leading-snug">
                            {{ $b->e_border ?? '—' }}
                        </p>
                        @if ($b->e_dim)
                            <p class="text-secondary data-tabular mt-0.5">{{ $b->e_dim }} م</p>
                        @endif
                    </div>

                    {{-- Bottom row: south --}}
                    <div></div>
                    <div class="bg-surface-container dark:bg-white/5 rounded-xl p-2">
                        <p class="text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('parcels.s_border') }}
                        </p>
                        <p class="font-semibold text-on-surface dark:text-white leading-snug">
                            {{ $b->s_border ?? '—' }}
                        </p>
                        @if ($b->s_dim)
                            <p class="text-secondary data-tabular mt-0.5">{{ $b->s_dim }} م</p>
                        @endif
                    </div>
                    <div></div>

                </div>

                {{-- Extra info --}}
                <dl class="space-y-2 text-sm border-t border-outline-variant dark:border-white/10 pt-3">
                    @if ($b->measured_area)
                        <div class="flex justify-between gap-2">
                            <dt class="text-on-surface-variant dark:text-on-primary-container">
                                {{ __('parcels.measured_area') }}
                            </dt>
                            <dd class="font-semibold text-on-surface dark:text-white data-tabular">
                                {{ number_format((float) $b->measured_area, 0) }} {{ __('dashboard.area_unit_sqm') }}
                            </dd>
                        </div>
                    @endif
                    @if ($b->engineeringOffice)
                        <div class="flex justify-between gap-2">
                            <dt class="text-on-surface-variant dark:text-on-primary-container">
                                {{ __('parcels.engineering_office') }}
                            </dt>
                            <dd class="font-medium text-on-surface dark:text-white text-end">
                                {{ $b->engineeringOffice->name }}
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif
        </div>

        {{-- Photos --}}
        @if ($parcel->photos->isNotEmpty())
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5
                        border border-outline-variant dark:border-white/10 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <span class="material-symbols-outlined text-[18px] text-tertiary-container"
                          style="font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;">
                        photo_library
                    </span>
                    <h2 class="font-semibold text-on-surface dark:text-white text-sm">
                        {{ __('parcels.photos_section') }}
                    </h2>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($parcel->photos as $photo)
                        <div class="aspect-square rounded-xl overflow-hidden bg-surface-container dark:bg-white/5">
                            <img src="{{ $photo->url ?? '#' }}"
                                 alt="{{ __('parcels.photos_section') }}"
                                 class="w-full h-full object-cover" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

</div>

@else
    <div class="flex flex-col items-center justify-center py-32 gap-4
                text-on-surface-variant dark:text-on-primary-container">
        <span class="material-symbols-outlined text-[56px] opacity-30">lock</span>
        <p class="text-sm">{{ __('permissions.unauthorized') }}</p>
    </div>
@endcan

@endsection
