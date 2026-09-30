@extends('layouts.auth')

@section('title', __('portal.login_title'))

@section('form-section')

    @include('portal.auth.partials.steps', ['current' => 1])

    <div class="mb-8">
        <h1 class="text-4xl font-bold mb-2 bg-gradient-to-l from-primary via-primary-container to-secondary dark:from-white dark:via-primary-fixed-dim dark:to-secondary-fixed-dim bg-clip-text text-transparent">
            {{ __('portal.login_title') }}
        </h1>
        <p class="text-base text-on-surface-variant dark:text-on-primary-container">
            {{ __('portal.login_subtitle') }}
        </p>
    </div>

    @if ($errors->any())
        <div class="flex items-start gap-2 bg-error-container text-on-error-container rounded-2xl px-4 py-3 mb-6 text-sm">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.login.submit') }}" class="space-y-5">
        @csrf

        <div class="space-y-1.5">
            <label for="phone"
                   class="block text-xs font-semibold tracking-wider uppercase text-on-surface-variant dark:text-primary-fixed-dim">
                {{ __('portal.phone') }}
            </label>
            <div class="relative group rounded-2xl p-[1.5px] bg-outline-variant dark:bg-white/15 focus-within:bg-gradient-to-l focus-within:from-secondary focus-within:to-tertiary-container transition-colors">
                <div class="relative rounded-[15px] bg-surface-container-lowest dark:bg-[#0b1626]">
                    <span class="material-symbols-outlined absolute start-4 top-1/2 -translate-y-1/2 text-[22px] text-outline group-focus-within:text-secondary transition-colors pointer-events-none">smartphone</span>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                           placeholder="{{ __('portal.phone_placeholder') }}"
                           autocomplete="tel" inputmode="tel" autofocus required dir="ltr"
                           class="w-full bg-transparent border-0 rounded-[15px] py-4 ps-12 pe-4 text-lg tracking-[0.12em] data-tabular text-on-surface dark:text-white placeholder:tracking-normal placeholder:text-outline dark:placeholder:text-on-primary-container/50 focus:outline-none focus:ring-0">
                </div>
            </div>
        </div>

        <button type="submit"
                class="group w-full flex items-center justify-center gap-2 bg-gradient-to-l from-secondary to-on-secondary-container hover:brightness-110 text-white font-semibold rounded-2xl py-4 text-base transition-all shadow-lg shadow-secondary/30 active:scale-[0.98] mt-2">
            {{ __('portal.login_button') }}
            <span class="material-symbols-outlined text-[20px] transition-transform group-hover:-translate-x-1 ltr:rotate-180 ltr:group-hover:translate-x-1">arrow_back</span>
        </button>
    </form>

    <p class="flex items-center justify-center gap-1.5 mt-6 text-xs text-on-surface-variant dark:text-on-primary-container">
        <span class="material-symbols-outlined text-[16px] text-secondary">lock</span>{{ __('portal.trust_note') }}
    </p>

@endsection

@include('portal.auth.partials.brand', ['tagline' => __('portal.brand_tagline')])
