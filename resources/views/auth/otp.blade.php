@extends('layouts.auth')

@section('title', __('auth.otp_title'))

@section('form-section')

    <div class="mb-10">
        <h1 class="text-4xl font-bold text-on-surface dark:text-white mb-2">
            {{ __('auth.otp_title') }}
        </h1>
        <p class="text-base text-on-surface-variant dark:text-on-primary-container">
            {{ __('auth.otp_subtitle', ['destination' => __('auth.email')]) }}
        </p>
    </div>

    @if (session('resent'))
        <div class="bg-secondary-container text-on-secondary-container rounded-xl px-4 py-3 mb-6 text-sm">
            {{ __('auth.otp_resent') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-error-container text-on-error-container rounded-xl px-4 py-3 mb-6 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- OTP verify form --}}
    <form method="POST" action="{{ route('otp.verify') }}" id="otp-form" class="space-y-8">
        @csrf
        <input type="hidden" name="otp" id="otp-hidden">

        {{-- 6 separate digit boxes --}}
        <div class="flex justify-between gap-3" dir="ltr">
            @for ($i = 0; $i < 6; $i++)
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                       class="otp-input w-12 h-16 md:w-14 md:h-20 text-center text-2xl font-bold bg-surface-container-low dark:bg-white/5 border-2 border-outline-variant dark:border-white/20 rounded-xl text-on-surface dark:text-white transition-all focus:border-secondary focus:outline-none focus:ring-2 focus:ring-secondary/20"
                       {{ $i === 0 ? 'autofocus' : '' }}>
            @endfor
        </div>

        <button type="submit"
                class="w-full bg-secondary hover:brightness-110 text-white font-semibold rounded-xl py-4 text-base transition-all shadow-lg active:scale-[0.98]">
            {{ __('auth.otp_verify') }}
        </button>

    </form>

    {{-- Resend row (separate form — not nested) --}}
    <div class="flex items-center justify-center gap-3 text-sm mt-6 text-on-surface dark:text-primary-fixed-dim">
        <span>{{ __('auth.otp_no_code') }}</span>
        <form method="POST" action="{{ route('otp.resend') }}" class="inline">
            @csrf
            <button type="submit" id="resend-btn" disabled
                    class="font-semibold text-secondary opacity-50 cursor-not-allowed transition-all">
                {{ __('auth.otp_resend') }}
            </button>
        </form>
        <span id="otp-timer" class="font-mono text-on-surface-variant dark:text-on-primary-container">
            {{ floor($resendSeconds / 60) }}:{{ str_pad($resendSeconds % 60, 2, '0', STR_PAD_LEFT) }}
        </span>
    </div>

@endsection

{{-- Same city map atmosphere as login --}}
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
        {{ __('auth.otp_brand_desc') }}
    </p>
@endsection

@push('scripts')
<script>
    const inputs = document.querySelectorAll('.otp-input');
    const hiddenOtp = document.getElementById('otp-hidden');
    const otpForm = document.getElementById('otp-form');

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/, '');
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });

    otpForm.addEventListener('submit', () => {
        hiddenOtp.value = Array.from(inputs).map(i => i.value).join('');
    });

    let seconds = {{ $resendSeconds }};
    const timerEl = document.getElementById('otp-timer');
    const resendBtn = document.getElementById('resend-btn');

    const countdown = setInterval(() => {
        seconds--;
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        timerEl.textContent = m + ':' + (s < 10 ? '0' + s : s);

        if (seconds <= 0) {
            clearInterval(countdown);
            timerEl.classList.add('hidden');
            resendBtn.disabled = false;
            resendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            resendBtn.classList.add('cursor-pointer');
        }
    }, 1000);
</script>
@endpush
