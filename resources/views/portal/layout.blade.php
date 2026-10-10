<!DOCTYPE html>
<html
    dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}"
    lang="{{ app()->getLocale() }}"
    x-data="{
        isDark: (function () {
            var t = localStorage.getItem('theme');
            return t === 'dark' || (! t && window.matchMedia('(prefers-color-scheme: dark)').matches);
        })(),
        toggleTheme () {
            this.isDark = ! this.isDark;
            localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
            // After the class lands, so maps and charts redraw in the new theme.
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('sakuki:theme-changed', { detail: { dark: this.isDark } })));
        },
        sidebarOpen: window.matchMedia('(min-width: 1024px)').matches,
    }"
    :class="{ 'dark': isDark }"
>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('portal.dashboard_title')) — {{ __('nav.app_name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}">

    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark' || (! t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        }());
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Alpine.js ships bundled inside Livewire's own script, not app.js —
         without this, every x-data directive on the page (the theme toggle,
         the sidebar, the chart cards) silently does nothing. --}}
    @livewireStyles
</head>
<body class="bg-surface dark:bg-[#0b111c] text-on-surface dark:text-white min-h-screen bg-[radial-gradient(ellipse_at_top_left,rgba(104,219,174,0.10),transparent_45%),radial-gradient(ellipse_at_bottom_right,rgba(201,168,76,0.10),transparent_45%)] bg-fixed">

    {{-- Sidebar — the same fixed w-[280px] navy panel as the internal
         dashboard's, trimmed to the two things an owner has: their own
         parcels, and the home screen. No permission checks — every owner
         sees the same two items. --}}
    <aside class="fixed inset-y-0 start-0 w-[280px] z-50 flex flex-col select-none shadow-2xl overflow-hidden bg-gradient-to-b from-primary via-primary to-on-secondary-fixed-variant"
           :class="{ 'translate-x-0': sidebarOpen, 'rtl:translate-x-full ltr:-translate-x-full': ! sidebarOpen }">

        {{-- The Kingdom drawn as a plat, and a gold glow, echoing the page heroes --}}
        <x-portal.ksa-map id="ksa-side" :cell="10" class="absolute start-1/2 -translate-x-1/2 rtl:translate-x-1/2 top-1/2 -translate-y-1/2 w-[330px] text-white opacity-[0.13]" />
        <div class="absolute -bottom-24 -start-16 w-72 h-72 rounded-full bg-secondary-fixed-dim/15 blur-3xl pointer-events-none" aria-hidden="true"></div>

        <div class="relative flex items-center gap-2 px-4 h-16 border-b border-white/10 shrink-0">
            <img src="{{ asset('images/logo-icon-sm.png') }}" alt="{{ __('nav.app_name') }}" class="h-10 w-auto object-contain">
            <span class="text-white font-bold text-lg">{{ __('nav.app_name') }}</span>
        </div>

        <nav class="relative flex-grow overflow-y-auto py-5 px-3 space-y-1">
            @foreach ([
                ['route' => 'portal.dashboard', 'label' => 'portal.nav_dashboard', 'icon' => 'grid_view'],
                ['route' => 'portal.parcels.index', 'label' => 'portal.nav_parcels', 'icon' => 'map'],
                ...(($hasLinkedParcels ?? false) ? [['route' => 'portal.linked.index', 'label' => 'portal.nav_linked', 'icon' => 'family_restroom']] : []),
                ['route' => 'portal.documents.index', 'label' => 'portal.nav_documents', 'icon' => 'folder'],
                ['route' => 'portal.modification-requests.index', 'label' => 'portal.nav_requests', 'icon' => 'edit_note'],
                ['route' => 'portal.profile', 'label' => 'portal.nav_profile', 'icon' => 'person'],
            ] as $item)
                @php
                    $isActive = request()->routeIs($item['route'])
                        || ($item['route'] === 'portal.parcels.index' && request()->routeIs('portal.parcels.*'))
                        || ($item['route'] === 'portal.linked.index' && request()->routeIs('portal.linked.*'));
                @endphp
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                          {{ $isActive
                              ? 'bg-gradient-to-l from-tertiary-container/35 to-white/5 text-white ring-1 ring-tertiary-container/40 shadow-[0_0_18px_-4px_rgba(201,168,76,0.55)]'
                              : 'text-primary-fixed-dim hover:bg-white/10 hover:text-white hover:translate-x-[-2px]' }}"
                   {{ $isActive ? 'aria-current=page' : '' }}>
                    <span class="material-symbols-outlined text-[22px] shrink-0"
                          style="font-variation-settings: 'FILL' {{ $isActive ? 1 : 0 }}, 'wght' 300, 'GRAD' 0, 'opsz' 24;">
                        {{ $item['icon'] }}
                    </span>
                    <span>{{ __($item['label']) }}</span>
                </a>
            @endforeach

            {{-- Services — the same catalogue as the dashboard's sidebar group. --}}
            <div x-data="{ servicesOpen: {{ request()->routeIs('portal.services.*') ? 'true' : 'false' }} }" class="pt-4 mt-3 border-t border-white/10">
                <button type="button" @click="servicesOpen = ! servicesOpen"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                               text-primary-fixed-dim hover:bg-white/8 hover:text-white transition-all duration-150">
                    <span class="material-symbols-outlined text-[22px] shrink-0">home_repair_service</span>
                    <span class="flex-1 text-start">{{ __('nav.services') }}</span>
                    <span class="material-symbols-outlined text-[18px] shrink-0 transition-transform duration-200"
                          :class="servicesOpen && 'rotate-180'">expand_more</span>
                </button>
                <div x-show="servicesOpen" x-cloak class="ps-3 mt-0.5 space-y-0.5">
                    @foreach ([
                        ['nav.services_survey_request', 'straighten', 'survey-request', false],
                        ['nav.services_engineering_design', 'architecture', 'engineering-design', false],
                        ['nav.services_solar_energy', 'solar_power', 'solar-energy', false],
                        ['nav.services_valuation', 'assessment', 'valuation', true],
                        ['nav.services_investment', 'trending_up', 'investment', true],
                        ['nav.services_municipal', 'apartment', 'municipal', false],
                    ] as [$label, $icon, $slug, $soon])
                        @php $serviceActive = request()->routeIs('portal.services.'.$slug); @endphp
                        <a href="{{ route('portal.services.'.$slug) }}"
                           class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-all duration-150
                                  {{ $serviceActive ? 'bg-white/15 text-white' : 'text-primary-fixed-dim hover:bg-white/8 hover:text-white' }}">
                            <span class="material-symbols-outlined text-[19px] shrink-0">{{ $icon }}</span>
                            <span class="flex-1">{{ __($label) }}</span>
                            @if ($soon)
                                <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-tertiary-container/40 text-tertiary-container shrink-0">
                                    {{ __('nav.services_coming_soon_badge') }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>

        <div class="relative px-3 pb-4 shrink-0">
            <div class="flex items-center gap-3 rounded-2xl p-3 bg-white/10 ring-1 ring-white/10 backdrop-blur-sm">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-tertiary-container to-tertiary flex items-center justify-center shrink-0 text-white text-sm font-bold">
                    {{ mb_substr(auth('owner')->user()?->name ?? 'م', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ auth('owner')->user()?->name }}</p>
                </div>
                <form method="POST" action="{{ route('portal.logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="text-primary-fixed-dim hover:text-white transition-colors" title="{{ __('portal.logout') }}">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Topbar --}}
    @php
        $hijriToday = extension_loaded('intl')
            ? (new \IntlDateFormatter(app()->getLocale().'@calendar=islamic-umalqura', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'Asia/Riyadh', \IntlDateFormatter::TRADITIONAL))->format(time())
            : null;
    @endphp
    <header class="fixed top-0 end-0 start-0 lg:start-[280px] h-16 z-40
                    bg-white/70 dark:bg-[#0d1420]/70 backdrop-blur-xl border-b border-outline-variant/60 dark:border-white/10
                    flex items-center justify-between px-5">
        <button type="button" @click="sidebarOpen = ! sidebarOpen" class="lg:hidden text-on-surface dark:text-white">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <div class="hidden lg:flex items-center gap-3 text-sm">
            <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-surface-container dark:bg-white/5">
                <span class="material-symbols-outlined text-[17px] text-tertiary">calendar_month</span>
                @if ($hijriToday)<span>{{ $hijriToday }}</span><span class="text-on-surface-variant dark:text-on-primary-container">·</span>@endif
                <span class="data-tabular text-on-surface-variant dark:text-on-primary-container" dir="ltr">{{ now('Asia/Riyadh')->format('Y-m-d') }}</span>
            </span>
        </div>
        <div class="flex items-center gap-3">
            <button @click="toggleTheme()" class="text-on-surface-variant dark:text-on-primary-container hover:text-secondary transition-colors">
                <span class="material-symbols-outlined text-[20px]" x-text="isDark ? 'light_mode' : 'dark_mode'">dark_mode</span>
            </button>
            <a href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}"
               class="text-sm font-medium text-on-surface-variant dark:text-on-primary-container hover:text-secondary transition-colors">
                {{ app()->isLocale('ar') ? 'EN' : 'ع' }}
            </a>
        </div>
    </header>

    <main class="ms-0 lg:ms-[280px] mt-16 p-5 lg:p-7 max-w-[1680px]">
        @yield('content')
    </main>

    <script>
        // An ApexCharts chart built from options for the current theme, and
        // rebuilt whenever the theme toggle switches it.
        window.themedChart = (el, build) => {
            let chart = new ApexCharts(el, build(document.documentElement.classList.contains('dark')));
            chart.render();
            window.addEventListener('sakuki:theme-changed', ({ detail }) => {
                chart.destroy();
                chart = new ApexCharts(el, build(detail.dark));
                chart.render();
            });
            return chart;
        };

        // Figures on the hero bands count up from zero once, on first paint.
        document.addEventListener('alpine:init', () => {
            Alpine.data('countUp', (target, decimals = 0) => ({
                display: (0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }),
                init() {
                    const start = performance.now();
                    const duration = 1100;
                    const tick = (now) => {
                        const t = Math.min(1, (now - start) / duration);
                        const eased = 1 - Math.pow(1 - t, 3);
                        this.display = (target * eased).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                        if (t < 1) requestAnimationFrame(tick);
                    };
                    requestAnimationFrame(tick);
                },
            }));
        });
    </script>
    @stack('scripts')
    @livewireScripts

</body>
</html>
