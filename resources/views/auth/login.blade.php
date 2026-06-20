@extends('layouts.auth')

@section('title', __('auth.login_title'))

@section('form-section')

    <div class="mb-10">
        <h1 class="text-4xl font-bold text-on-surface dark:text-white mb-2">
            {{ __('auth.login_title') }}
        </h1>
        <p class="text-base text-on-surface-variant dark:text-on-primary-container">
            {{ __('auth.login_subtitle') }}
        </p>
    </div>

    @if ($errors->any())
        <div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-6 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        {{-- Email --}}
        <div class="space-y-1.5">
            <label for="email"
                   class="block text-xs font-semibold tracking-wider uppercase text-on-surface-variant dark:text-primary-fixed-dim">
                {{ __('auth.email') }}
            </label>
            <div class="relative group">
                <span class="material-symbols-outlined absolute start-4 top-1/2 -translate-y-1/2 text-[20px] text-outline dark:text-outline group-focus-within:text-secondary transition-colors pointer-events-none">mail</span>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       placeholder="{{ __('auth.email_placeholder') }}"
                       autocomplete="email" autofocus required dir="ltr"
                       class="w-full bg-surface-container-low dark:bg-white/5 border border-outline-variant dark:border-white/15 rounded-xl py-4 ps-12 pe-4 text-on-surface dark:text-white placeholder:text-outline dark:placeholder:text-on-primary-container/50 transition-all focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/20">
            </div>
        </div>

        {{-- Password --}}
        <div class="space-y-1.5" x-data="{ showPwd: false }">
            <label for="password"
                   class="block text-xs font-semibold tracking-wider uppercase text-on-surface-variant dark:text-primary-fixed-dim">
                {{ __('auth.password') }}
            </label>
            <div class="relative group">
                <span class="material-symbols-outlined absolute start-4 top-1/2 -translate-y-1/2 text-[20px] text-outline dark:text-outline group-focus-within:text-secondary transition-colors pointer-events-none">lock</span>
                <input id="password" :type="showPwd ? 'text' : 'password'" name="password"
                       placeholder="{{ __('auth.password_placeholder') }}"
                       autocomplete="current-password" required
                       class="w-full bg-surface-container-low dark:bg-white/5 border border-outline-variant dark:border-white/15 rounded-xl py-4 ps-12 pe-12 text-on-surface dark:text-white placeholder:text-outline dark:placeholder:text-on-primary-container/50 transition-all focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/20">
                <button type="button" @click="showPwd = !showPwd"
                        class="absolute end-4 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface dark:text-outline dark:hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-[20px]"
                          x-text="showPwd ? 'visibility_off' : 'visibility'">visibility</span>
                </button>
            </div>
        </div>

        <button type="submit"
                class="w-full bg-secondary hover:brightness-110 text-white font-semibold rounded-xl py-4 text-base transition-all shadow-lg active:scale-[0.98] mt-2">
            {{ __('auth.login_button') }}
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
    <img src="{{ asset('images/logo2.jpeg') }}" alt="صكوكي"
         class="rounded-2xl shadow-2xl"
         style="height:auto;width:320px;display:block;">
    <p class="text-primary-fixed-dim text-lg max-w-xs leading-relaxed mt-6">
        {{ __('auth.brand_tagline') }}
    </p>
@endsection
