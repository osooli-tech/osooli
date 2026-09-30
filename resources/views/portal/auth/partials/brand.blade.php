{{-- The navy half of the owner sign-in screens: the Kingdom traced as a plat
     behind the logo, and what the portal gives an owner. Shared by the phone
     and code steps; $tagline is the line under the logo. --}}
@section('brand-overlay')
    <div class="absolute inset-0 z-0 pointer-events-none bg-[radial-gradient(ellipse_at_30%_20%,rgba(104,219,174,0.16),transparent_55%),radial-gradient(ellipse_at_80%_85%,rgba(201,168,76,0.18),transparent_50%)]"></div>
    <x-portal.ksa-map id="ksa-auth" :cell="9" :animate="true"
                      class="absolute z-0 top-1/2 start-1/2 -translate-x-1/2 rtl:translate-x-1/2 -translate-y-1/2 w-[92%] text-primary-fixed-dim opacity-45" />
@endsection

@section('brand-panel')
    <span class="block bg-white rounded-3xl px-5 py-4 shadow-2xl shadow-black/30 ring-1 ring-white/40">
        <img src="{{ asset('images/logo-full-sm.png') }}" alt="{{ __('nav.app_name') }}" class="w-36 h-auto block">
    </span>
    <p class="text-2xl font-bold text-white leading-snug max-w-sm">{{ __('portal.brand_headline') }}</p>
    <p class="text-primary-fixed-dim text-base max-w-xs leading-relaxed">{{ $tagline }}</p>

    <ul class="w-full max-w-sm space-y-2.5 pb-10 text-start">
        @foreach ([
            ['icon' => 'workspace_premium', 'key' => 'deeds', 'tone' => 'text-tertiary-fixed-dim'],
            ['icon' => 'view_in_ar', 'key' => 'twin', 'tone' => 'text-secondary-fixed-dim'],
            ['icon' => 'verified_user', 'key' => 'secure', 'tone' => 'text-primary-fixed-dim'],
        ] as $i => $feature)
            <li class="auth-rise flex items-center gap-3 rounded-2xl px-4 py-3 bg-white/[0.07] backdrop-blur-md ring-1 ring-white/10"
                style="animation-delay: {{ 0.6 + $i * 0.18 }}s">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-white/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[22px] {{ $feature['tone'] }}">{{ $feature['icon'] }}</span>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-white">{{ __('portal.feature_'.$feature['key'].'_title') }}</span>
                    <span class="block text-xs text-primary-fixed-dim">{{ __('portal.feature_'.$feature['key'].'_desc') }}</span>
                </span>
            </li>
        @endforeach
    </ul>

    <style>
        .auth-rise { opacity: 0; transform: translateY(12px); animation: auth-rise 0.7s ease-out forwards; }
        @keyframes auth-rise { to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .auth-rise { animation: none; opacity: 1; transform: none; } }
    </style>
@endsection
