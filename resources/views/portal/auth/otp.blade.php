@extends('layouts.auth')

@section('title', __('portal.otp_title'))

@section('form-section')

    @include('portal.auth.partials.steps', ['current' => 2])

    <div class="mb-8">
        <h1 class="text-4xl font-bold mb-2 bg-gradient-to-l from-primary via-primary-container to-secondary dark:from-white dark:via-primary-fixed-dim dark:to-secondary-fixed-dim bg-clip-text text-transparent">
            {{ __('portal.otp_title') }}
        </h1>
        <p class="text-base text-on-surface-variant dark:text-on-primary-container">
            @if ($maskedPhone)
                {{ __('portal.otp_sent_to') }} <span class="font-semibold text-on-surface dark:text-white data-tabular" dir="ltr">{{ $maskedPhone }}</span>
            @else
                {{ __('portal.otp_subtitle') }}
            @endif
        </p>
        <a href="{{ route('portal.login') }}" class="inline-flex items-center gap-1 mt-2 text-sm font-medium text-secondary hover:underline">
            <span class="material-symbols-outlined text-[16px]">edit</span>{{ __('portal.otp_change_number') }}
        </a>
    </div>

    @if (session('resent'))
        <div class="flex items-center gap-2 bg-secondary-container text-on-secondary-container rounded-2xl px-4 py-3 mb-6 text-sm">
            <span class="material-symbols-outlined text-[18px]">mark_email_read</span>{{ __('portal.otp_resent') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="flex items-start gap-2 bg-error-container text-on-error-container rounded-2xl px-4 py-3 mb-6 text-sm">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('portal.otp.verify') }}" id="otp-form" class="space-y-8">
        @csrf
        <input type="hidden" name="otp" id="otp-hidden">

        <div class="flex justify-center gap-4" dir="ltr">
            @for ($i = 0; $i < $codeLength; $i++)
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                       aria-label="{{ __('portal.otp_digit', ['n' => $i + 1]) }}"
                       class="otp-input w-16 h-20 md:w-[4.5rem] md:h-24 text-center text-4xl font-bold data-tabular rounded-2xl
                              bg-surface-container-lowest dark:bg-[#0b1626] border-2 border-outline-variant dark:border-white/15
                              text-on-surface dark:text-white shadow-sm transition-all duration-200
                              focus:border-secondary focus:outline-none focus:ring-4 focus:ring-secondary/15 focus:-translate-y-1"
                       {{ $i === 0 ? 'autofocus' : '' }}>
            @endfor
        </div>

        <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-gradient-to-l from-secondary to-on-secondary-container hover:brightness-110 text-white font-semibold rounded-2xl py-4 text-base transition-all shadow-lg shadow-secondary/30 active:scale-[0.98]">
            <span class="material-symbols-outlined text-[20px]">verified</span>{{ __('portal.otp_verify') }}
        </button>
    </form>

    <div class="flex items-center justify-center gap-3 text-sm mt-6 text-on-surface dark:text-primary-fixed-dim">
        {{-- Countdown ring: empties as the code's lifetime runs out --}}
        <span class="relative w-11 h-11 shrink-0" id="otp-ring">
            <svg viewBox="0 0 36 36" class="w-11 h-11 -rotate-90" aria-hidden="true">
                <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-width="3" class="text-outline-variant/60 dark:text-white/10" />
                <circle id="otp-ring-bar" cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                        class="text-secondary transition-[stroke-dashoffset] duration-1000 ease-linear" stroke-dasharray="97.4" stroke-dashoffset="0" />
            </svg>
            <span id="otp-timer" class="absolute inset-0 flex items-center justify-center text-[10px] font-bold data-tabular text-on-surface-variant dark:text-on-primary-container">
                {{ floor($resendSeconds / 60) }}:{{ str_pad((string) ($resendSeconds % 60), 2, '0', STR_PAD_LEFT) }}
            </span>
        </span>
        <span>{{ __('portal.otp_no_code') }}</span>
        <form method="POST" action="{{ route('portal.otp.resend') }}" class="inline">
            @csrf
            <button type="submit" id="resend-btn" disabled
                    class="font-semibold text-secondary opacity-50 cursor-not-allowed transition-all">
                {{ __('portal.otp_resend') }}
            </button>
        </form>
    </div>

@endsection

@include('portal.auth.partials.brand', ['tagline' => __('portal.otp_brand_desc')])

@push('scripts')
<script>
    const inputs = [...document.querySelectorAll('.otp-input')];
    const hiddenOtp = document.getElementById('otp-hidden');
    const otpForm = document.getElementById('otp-form');

    // Fills the boxes from a string of digits (typed or pasted) and sends the
    // form once every box holds one.
    const fill = (from, digits) => {
        digits.split('').forEach((d, k) => { if (inputs[from + k]) inputs[from + k].value = d; });
        const next = inputs.find((i) => ! i.value);
        (next ?? inputs[inputs.length - 1]).focus();
        if (! next) {
            hiddenOtp.value = inputs.map((i) => i.value).join('');
            otpForm.requestSubmit();
        }
    };

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const digits = e.target.value.replace(/\D/g, '');
            e.target.value = '';
            if (digits) fill(index, digits);
        });
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            fill(0, (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, inputs.length));
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && ! e.target.value && index > 0) inputs[index - 1].focus();
        });
    });

    otpForm.addEventListener('submit', () => {
        hiddenOtp.value = inputs.map((i) => i.value).join('');
    });

    const total = {{ $resendSeconds }};
    let seconds = total;
    const timerEl = document.getElementById('otp-timer');
    const ringBar = document.getElementById('otp-ring-bar');
    const resendBtn = document.getElementById('resend-btn');

    const countdown = setInterval(() => {
        seconds--;
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        timerEl.textContent = m + ':' + (s < 10 ? '0' + s : s);
        ringBar.setAttribute('stroke-dashoffset', String(97.4 * (1 - seconds / total)));

        if (seconds <= 0) {
            clearInterval(countdown);
            document.getElementById('otp-ring').classList.add('hidden');
            resendBtn.disabled = false;
            resendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            resendBtn.classList.add('cursor-pointer');
        }
    }, 1000);
</script>
@endpush
