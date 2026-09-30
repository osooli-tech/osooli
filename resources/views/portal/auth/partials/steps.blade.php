{{-- Two-step progress for owner sign-in: phone, then code. $current is 1 or 2. --}}
<ol class="flex items-center gap-3 mb-8 text-xs font-semibold">
    @foreach ([1 => 'step_phone', 2 => 'step_code'] as $step => $key)
        @php $done = $step < $current; $active = $step === $current; @endphp
        <li class="flex items-center gap-2 {{ $active || $done ? 'text-secondary' : 'text-outline dark:text-on-primary-container/60' }}">
            <span class="w-7 h-7 rounded-full flex items-center justify-center data-tabular
                         {{ $done ? 'bg-secondary text-white' : ($active ? 'bg-secondary/15 ring-2 ring-secondary' : 'bg-surface-container dark:bg-white/5') }}">
                @if ($done)<span class="material-symbols-outlined text-[16px]">check</span>@else{{ $step }}@endif
            </span>
            {{ __('portal.'.$key) }}
        </li>
        @if ($step === 1)
            <li class="flex-1 h-0.5 rounded-full {{ $current > 1 ? 'bg-secondary' : 'bg-outline-variant dark:bg-white/10' }}" aria-hidden="true"></li>
        @endif
    @endforeach
</ol>
