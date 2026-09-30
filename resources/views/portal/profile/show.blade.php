@extends('portal.layout')

@section('title', __('portal.profile_title'))

@section('content')
@php
    $cardCls = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl border border-outline-variant dark:border-white/10 shadow-sm';
    $inputCls = 'w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10 text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40';
    $labelCls = 'block text-xs font-medium mb-1 text-on-surface-variant dark:text-on-primary-container';
@endphp
<div class="space-y-5">

    {{-- Identity header --}}
    <div class="{{ $cardCls }} overflow-hidden">
        <div class="relative h-24 overflow-hidden bg-gradient-to-br from-primary via-primary-container to-on-secondary-fixed-variant">
            <div class="absolute -top-16 end-10 w-56 h-56 rounded-full bg-tertiary-container/25 blur-3xl"></div>
            <div class="absolute -bottom-20 start-0 w-64 h-64 rounded-full bg-secondary-fixed-dim/20 blur-3xl"></div>
        </div>
        <div class="px-5 pb-5 flex flex-wrap items-start gap-4">
            <div class="-mt-10 w-20 h-20 rounded-2xl bg-secondary text-white text-3xl font-bold flex items-center justify-center
                        ring-4 ring-surface-container-lowest dark:ring-[#1a1f2e] shrink-0">
                {{ mb_substr($owner->name, 0, 1) }}
            </div>
            <div class="flex-1 min-w-0 pt-3">
                <h1 class="text-lg font-bold truncate">{{ $owner->name }}</h1>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-on-surface-variant dark:text-on-primary-container mt-1">
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px]">call</span><span dir="ltr">{{ $owner->phone ?? '—' }}</span></span>
                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px]">badge</span><span dir="ltr">{{ $owner->national_id ?? '—' }}</span></span>
                    @if ($owner->email)
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[15px]">mail</span><span dir="ltr">{{ $owner->email }}</span></span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- At a glance --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([
            ['icon' => 'map', 'label' => __('portal.nav_parcels'), 'value' => $stats['parcels'], 'href' => route('portal.parcels.index')],
            ['icon' => 'description', 'label' => __('dashboard.total_deeds'), 'value' => $stats['deeds'], 'href' => route('portal.parcels.index')],
            ['icon' => 'folder', 'label' => __('portal.nav_documents'), 'value' => $stats['documents'], 'href' => route('portal.documents.index')],
            ['icon' => 'pending_actions', 'label' => __('portal.profile_pending_requests'), 'value' => $stats['pending_requests'], 'href' => route('portal.modification-requests.index')],
        ] as $tile)
            <a href="{{ $tile['href'] }}" class="{{ $cardCls }} p-4 hover:border-primary/40 transition-colors">
                <span class="material-symbols-outlined text-[22px] text-secondary">{{ $tile['icon'] }}</span>
                <p class="text-2xl font-bold data-tabular mt-1">{{ number_format($tile['value']) }}</p>
                <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ $tile['label'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        {{-- Contact details --}}
        <form method="POST" action="{{ route('portal.profile.update') }}" class="xl:col-span-2 {{ $cardCls }} p-5 space-y-4">
            @csrf
            @method('PUT')

            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">edit</span>
                <h2 class="font-semibold text-sm">{{ __('portal.profile_contact_section') }}</h2>
            </div>

            @if (session('status'))
                <p class="flex items-center gap-1.5 text-sm text-secondary font-medium">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>{{ session('status') }}
                </p>
            @endif

            <div>
                <label class="{{ $labelCls }}">{{ __('portal.profile_name') }}</label>
                <input type="text" name="name" value="{{ old('name', $owner->name) }}" required maxlength="255" class="{{ $inputCls }}">
                @error('name') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelCls }}">{{ __('portal.profile_email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $owner->email) }}" maxlength="255" dir="ltr" class="{{ $inputCls }}">
                    @error('email') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">{{ __('portal.profile_whatsapp') }}</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp', $owner->whatsapp) }}" maxlength="30" dir="ltr" class="{{ $inputCls }}">
                    @error('whatsapp') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <button type="submit" class="px-5 py-2 rounded-xl text-sm font-medium bg-primary text-white hover:opacity-90 transition-opacity">
                {{ __('portal.profile_save') }}
            </button>
        </form>

        {{-- Sign-in & security --}}
        <div class="{{ $cardCls }} p-5 space-y-3">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-secondary">shield_lock</span>
                <h2 class="font-semibold text-sm">{{ __('portal.profile_security_section') }}</h2>
            </div>
            <div class="rounded-xl bg-surface-container dark:bg-white/5 p-3 text-xs space-y-2">
                <p class="flex items-center gap-2"><span class="material-symbols-outlined text-[16px] text-secondary">sms</span>{{ __('portal.profile_signin_method') }}</p>
                <p class="flex items-center gap-2"><span class="material-symbols-outlined text-[16px] text-secondary">call</span><span dir="ltr">{{ $owner->phone ?? '—' }}</span></p>
            </div>
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal.profile_readonly_hint') }}</p>
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="{{ $cardCls }} p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-sm">{{ __('portal.profile_recent_requests') }}</h2>
                <a href="{{ route('portal.modification-requests.index') }}" class="text-xs text-primary hover:underline">{{ __('portal.profile_view_all') }}</a>
            </div>
            @forelse ($recentRequests as $req)
                <div class="flex items-center justify-between gap-3 py-2 text-sm border-b border-outline-variant dark:border-white/10 last:border-0">
                    <span class="min-w-0 truncate">{{ $req->fieldLabel() }} — <span class="data-tabular">{{ $req->parcel?->parcel_no }}</span></span>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-tertiary-container/30 shrink-0">{{ __('modification_requests.status.'.$req->status->value) }}</span>
                </div>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('portal.requests_empty') }}</p>
            @endforelse
        </div>

        <div class="{{ $cardCls }} p-5">
            <h2 class="font-semibold text-sm mb-3">{{ __('portal.profile_recent_downloads') }}</h2>
            @forelse ($recentDownloads as $log)
                <div class="flex items-center justify-between gap-3 py-2 text-sm border-b border-outline-variant dark:border-white/10 last:border-0">
                    <span class="flex items-center gap-2"><span class="material-symbols-outlined text-[16px] text-secondary">download</span>{{ __('portal.profile_document_downloaded') }}</span>
                    <span class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular" dir="ltr">{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                </div>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('portal.profile_no_downloads') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
