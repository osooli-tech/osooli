@extends('portal.layout')

@section('title', __('portal.requests_title'))

@section('content')
@php
    $count = fn (string $status): int => (int) ($statusCounts[$status] ?? 0);
    $total = (int) $statusCounts->sum();
    $statusTone = [
        'pending' => 'bg-tertiary-container/30 text-on-tertiary-container dark:text-tertiary-fixed',
        'sent_to_arcgis' => 'bg-primary/10 text-primary dark:text-primary-fixed-dim',
        'applied' => 'bg-secondary/10 text-secondary',
        'rejected' => 'bg-error/10 text-error',
    ];
@endphp
<div class="space-y-5">
    <x-portal.page-hero icon="edit_note" :title="__('portal.requests_title')" :subtitle="__('portal.requests_hero_subtitle')" id="requests">
        {{-- The journey every request takes, with how many of yours sit at each step --}}
        <div class="mt-6 flex flex-col md:flex-row items-stretch gap-3">
            @foreach ([
                ['status' => 'pending', 'icon' => 'hourglass_top'],
                ['status' => 'sent_to_arcgis', 'icon' => 'sync_alt'],
            ] as $step)
                <div class="flex-1 rounded-2xl bg-white/10 ring-1 ring-white/10 p-4">
                    <span class="material-symbols-outlined text-[22px] text-tertiary-fixed-dim">{{ $step['icon'] }}</span>
                    <p class="text-3xl font-bold data-tabular mt-1"><span x-data="countUp({{ $count($step['status']) }})" x-text="display">{{ $count($step['status']) }}</span></p>
                    <p class="text-sm text-primary-fixed-dim">{{ __('modification_requests.status.'.$step['status']) }}</p>
                </div>
                <div class="hidden md:flex items-center text-tertiary-fixed-dim" aria-hidden="true">
                    <span class="material-symbols-outlined text-[28px]">arrow_back</span>
                </div>
            @endforeach
            <div class="flex-1 grid grid-rows-2 gap-3">
                <div class="rounded-2xl bg-secondary/30 ring-1 ring-secondary-fixed-dim/30 px-4 py-2.5 flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-[20px] text-secondary-fixed">task_alt</span>{{ __('modification_requests.status.applied') }}</span>
                    <span class="text-2xl font-bold data-tabular">{{ $count('applied') }}</span>
                </div>
                <div class="rounded-2xl bg-error/25 ring-1 ring-error-container/30 px-4 py-2.5 flex items-center justify-between">
                    <span class="flex items-center gap-2 text-sm"><span class="material-symbols-outlined text-[20px] text-error-container">block</span>{{ __('modification_requests.status.rejected') }}</span>
                    <span class="text-2xl font-bold data-tabular">{{ $count('rejected') }}</span>
                </div>
            </div>
        </div>
        <p class="text-xs text-primary-fixed-dim mt-4">{{ __('portal.requests_total', ['count' => $total]) }}</p>
    </x-portal.page-hero>

    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl border border-outline-variant dark:border-white/10 shadow-sm overflow-x-auto">
        @if ($requests->isEmpty())
            <div class="flex flex-col items-center gap-3 py-16 text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[48px] opacity-30">edit_note</span>
                <p class="text-sm">{{ __('portal.requests_empty') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10 bg-surface-container dark:bg-[#1e2435]">
                        @foreach (['col_parcel', 'col_field', 'col_old', 'col_new', 'col_status', 'col_date'] as $col)
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('modification_requests.'.$col) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @foreach ($requests as $req)
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 data-tabular">
                                <a href="{{ route('portal.parcels.show', $req->parcel_id) }}" class="font-semibold text-primary dark:text-primary-fixed-dim hover:underline">{{ $req->parcel?->parcel_no }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $req->fieldLabel() }}</td>
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">{{ $req->old_value ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 font-medium">
                                    <span class="material-symbols-outlined text-[15px] text-secondary">arrow_back</span>{{ $req->new_value }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $statusTone[$req->status->value] ?? '' }}">{{ __('modification_requests.status.'.$req->status->value) }}</span>
                            </td>
                            <td class="px-4 py-3 data-tabular text-on-surface-variant dark:text-on-primary-container">{{ $req->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{ $requests->links() }}
</div>
@endsection
