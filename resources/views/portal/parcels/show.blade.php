@extends('portal.layout')

@section('title', __('parcels.parcel_no').' '.$parcel->parcel_no)

@section('content')
@php
    $deed = $parcel->currentDeed ?? $parcel->deeds->sortByDesc('id')->first();
    $owners = $parcel->deeds->flatMap(fn ($d) => $d->deedOwners)->pluck('owner')->filter()->unique('id');
@endphp
<div class="space-y-5">

    <div class="flex items-center gap-2">
        <a href="{{ route('portal.parcels.index') }}" class="text-on-surface-variant dark:text-on-primary-container hover:text-secondary">
            <span class="material-symbols-outlined text-[20px]">{{ app()->isLocale('ar') ? 'arrow_forward' : 'arrow_back' }}</span>
        </a>
        <h1 class="text-xl font-semibold">{{ __('parcels.parcel_no') }} {{ $parcel->parcel_no }}</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Parcel info --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-3">
                {{ __('parcels.parcel_info') }}
            </h2>
            <dl class="space-y-2.5 text-sm">
                @foreach ([
                    [__('parcels.spatial_id'), $parcel->geo_id, true],
                    [__('parcels.asset_type'), $parcel->asset_type, false],
                    [__('parcels.plan_no'), $parcel->plan?->plan_no, false],
                    [__('parcels.district'), $parcel->plan?->district?->name_ar, false],
                    [__('parcels.city'), $parcel->plan?->district?->city?->name_ar, false],
                ] as [$label, $value, $ltr])
                    <div class="flex justify-between items-start gap-2">
                        <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ $label }}</dt>
                        <dd class="font-semibold text-end {{ $ltr ? 'ltr' : '' }}" @if($ltr) dir="ltr" @endif>{{ $value ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Deed info --}}
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-3">
                {{ __('parcels.deed_no') }}
            </h2>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between items-start gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.deed_no') }}</dt>
                    <dd class="font-semibold data-tabular text-end">{{ $deed?->deed_no ?? '—' }}</dd>
                </div>
                <div class="flex justify-between items-start gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.deed_date') }}</dt>
                    <dd class="font-semibold data-tabular text-end">{{ $deed?->deed_date_hijri ?? '—' }}</dd>
                </div>
                <div class="flex justify-between items-start gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.area_deed') }}</dt>
                    <dd class="font-semibold data-tabular text-end">
                        {{ $deed?->deed_area ? number_format((float) $deed->deed_area) . ' ' . __('dashboard.area_unit_sqm') : '—' }}
                    </dd>
                </div>
                <div class="flex justify-between items-start gap-2">
                    <dt class="text-on-surface-variant dark:text-on-primary-container shrink-0">{{ __('parcels.deed_status') }}</dt>
                    <dd class="font-semibold text-end">{{ $deed?->deed_status ?? '—' }}</dd>
                </div>
                @if ($owners->count() > 1)
                    <div class="pt-2 border-t border-outline-variant dark:border-white/10">
                        <dt class="text-on-surface-variant dark:text-on-primary-container mb-1">{{ __('parcels.owner') }}</dt>
                        <dd class="font-semibold">{{ $owners->pluck('name')->implode('، ') }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        {{-- Boundaries & dimensions --}}
        @if ($parcel->boundary)
            <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 lg:col-span-2">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant dark:text-on-primary-container mb-3">
                    {{ __('portal.boundaries_dimensions') }}
                </h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline-variant dark:border-white/10">
                            <th class="text-start py-1.5 font-medium text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.border_direction') }}</th>
                            <th class="text-start py-1.5 font-medium text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.border_value') }}</th>
                            <th class="text-start py-1.5 font-medium text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.border_length') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([
                            [__('parcels.n_direction'), $parcel->boundary->n_border, $parcel->boundary->n_dim],
                            [__('parcels.s_direction'), $parcel->boundary->s_border, $parcel->boundary->s_dim],
                            [__('parcels.e_direction'), $parcel->boundary->e_border, $parcel->boundary->e_dim],
                            [__('parcels.w_direction'), $parcel->boundary->w_border, $parcel->boundary->w_dim],
                        ] as [$dir, $border, $dim])
                            <tr class="border-b border-outline-variant dark:border-white/5 last:border-0">
                                <td class="py-1.5">{{ $dir }}</td>
                                <td class="py-1.5 text-on-surface-variant dark:text-on-primary-container">{{ $border ?? '—' }}</td>
                                <td class="py-1.5 data-tabular">{{ $dim ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>

</div>
@endsection
