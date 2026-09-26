@extends('layouts.auth')

@section('title', __('portal.login_title'))

@section('form-section')

    <div class="mb-10">
        <h1 class="text-4xl font-bold text-on-surface dark:text-white mb-2">
            {{ __('portal.login_title') }}
        </h1>
        <p class="text-base text-on-surface-variant dark:text-on-primary-container">
            {{ __('portal.login_subtitle') }}
        </p>
    </div>

    @if ($errors->any())
        <div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-6 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.login.submit') }}" class="space-y-5">
        @csrf

        <div class="space-y-1.5">
            <label for="phone"
                   class="block text-xs font-semibold tracking-wider uppercase text-on-surface-variant dark:text-primary-fixed-dim">
                {{ __('portal.phone') }}
            </label>
            <div class="relative group">
                <span class="material-symbols-outlined absolute start-4 top-1/2 -translate-y-1/2 text-[20px] text-outline dark:text-outline group-focus-within:text-secondary transition-colors pointer-events-none">call</span>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                       placeholder="{{ __('portal.phone_placeholder') }}"
                       autocomplete="tel" autofocus required dir="ltr"
                       class="w-full bg-surface-container-low dark:bg-white/5 border border-outline-variant dark:border-white/15 rounded-xl py-4 ps-12 pe-4 text-on-surface dark:text-white placeholder:text-outline dark:placeholder:text-on-primary-container/50 transition-all focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/20">
            </div>
        </div>

        <button type="submit"
                class="w-full bg-secondary hover:brightness-110 text-white font-semibold rounded-xl py-4 text-base transition-all shadow-lg active:scale-[0.98] mt-2">
            {{ __('portal.login_button') }}
        </button>

    </form>

@endsection

@section('brand-overlay')
<div class="absolute inset-0 z-0 pointer-events-none"
     style="background-image: url('{{ asset('images/map-bg.png') }}'); background-size: cover; background-position: center; opacity: 0.25;"></div>
<div class="absolute inset-0 z-0 pointer-events-none"
     style="background: linear-gradient(to left, #002444 0%, rgba(0,36,68,0.6) 50%, #002444 100%);"></div>
@endsection

@section('brand-panel')
    <span style="display:block;background:#fff;border-radius:22px;padding:24px 28px;">
        <img src="{{ asset('images/logo-full-sm.png') }}" alt="{{ __('nav.app_name') }}"
             style="height:auto;width:280px;display:block;">
    </span>
    <p class="text-primary-fixed-dim text-lg max-w-xs leading-relaxed mt-6">
        {{ __('portal.brand_tagline') }}
    </p>
@endsection
