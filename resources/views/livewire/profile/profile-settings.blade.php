<div class="max-w-2xl">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-on-surface dark:text-white">{{ __('profile.title') }}</h1>
        <p class="text-sm text-on-surface-variant mt-1">{{ __('profile.subtitle') }}</p>
    </div>

    {{-- User card --}}
    @php
        /** @var \App\Models\User $user */
        $user = auth()->user();
    @endphp
    <div class="bg-primary text-on-primary rounded-2xl p-5 mb-6 flex items-center gap-4">
        <div class="w-14 h-14 rounded-full bg-primary-container flex items-center justify-center text-2xl font-bold text-on-primary-container shrink-0">
            {{ mb_substr($user->name, 0, 1) }}
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-lg truncate">{{ $user->name }}</p>
            <p class="text-sm opacity-80" dir="ltr">{{ $user->email }}</p>
        </div>
        <div class="text-end shrink-0">
            <p class="text-xs opacity-70">{{ __('profile.role_label') }}</p>
            <p class="text-sm font-medium">{{ $user->getRoleNames()->first() ?? '—' }}</p>
        </div>
    </div>

    {{-- ─── Section: Personal info ────────────────────────────── --}}
    <div class="bg-white dark:bg-[#0f2235] rounded-2xl shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-on-surface dark:text-white mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-lg">person</span>
            {{ __('profile.section_info') }}
        </h2>

        <div class="space-y-4">
            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-variant mb-1">{{ __('profile.name') }}</label>
                <input
                    type="text"
                    wire:model="name"
                    placeholder="{{ __('profile.name_placeholder') }}"
                    class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-surface dark:bg-[#1a2e42] text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                >
                @error('name') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Email (readonly) --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-variant mb-1">{{ __('profile.email') }}</label>
                <input
                    type="email"
                    value="{{ $user->email }}"
                    readonly
                    dir="ltr"
                    class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-surface-container dark:bg-[#1a2e42] text-on-surface-variant cursor-not-allowed text-sm"
                >
            </div>

            {{-- Phone --}}
            <div>
                <label class="block text-sm font-medium text-on-surface-variant mb-1">{{ __('profile.phone') }}</label>
                <input
                    type="text"
                    wire:model="phone"
                    placeholder="{{ __('profile.phone_placeholder') }}"
                    dir="ltr"
                    class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-surface dark:bg-[#1a2e42] text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                >
                @error('phone') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5 flex justify-end">
            <button
                wire:click="saveInfo"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 bg-primary text-on-primary px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-primary-container transition-colors disabled:opacity-60"
            >
                <span class="material-symbols-outlined text-base" wire:loading.class="animate-spin" wire:loading.attr="style" wire:target="saveInfo">save</span>
                {{ __('profile.save_info') }}
            </button>
        </div>
    </div>

    {{-- ─── Section: Password ──────────────────────────────────── --}}
    <div class="bg-white dark:bg-[#0f2235] rounded-2xl shadow-sm p-6 mb-5">
        <h2 class="text-base font-semibold text-on-surface dark:text-white mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-lg">lock</span>
            {{ __('profile.section_password') }}
        </h2>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-on-surface-variant mb-1">{{ __('profile.current_password') }}</label>
                <input
                    type="password"
                    wire:model="currentPassword"
                    class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-surface dark:bg-[#1a2e42] text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                >
                @error('currentPassword') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-on-surface-variant mb-1">{{ __('profile.new_password') }}</label>
                <input
                    type="password"
                    wire:model="newPassword"
                    class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-surface dark:bg-[#1a2e42] text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                >
                @error('newPassword') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-on-surface-variant mb-1">{{ __('profile.confirm_password') }}</label>
                <input
                    type="password"
                    wire:model="confirmPassword"
                    class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-surface dark:bg-[#1a2e42] text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary text-sm"
                >
                @error('confirmPassword') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-5 flex justify-end">
            <button
                wire:click="changePassword"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 bg-secondary text-on-secondary px-5 py-2.5 rounded-xl text-sm font-medium hover:opacity-90 transition-opacity disabled:opacity-60"
            >
                <span class="material-symbols-outlined text-base">key</span>
                {{ __('profile.save_password') }}
            </button>
        </div>
    </div>

    {{-- ─── Section: Language ──────────────────────────────────── --}}
    <div class="bg-white dark:bg-[#0f2235] rounded-2xl shadow-sm p-6">
        <h2 class="text-base font-semibold text-on-surface dark:text-white mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-lg">language</span>
            {{ __('profile.section_language') }}
        </h2>

        <div class="flex gap-3">
            <a
                href="{{ route('locale.switch', 'ar') }}"
                class="flex-1 text-center py-3 rounded-xl border-2 transition-colors text-sm font-medium
                    {{ app()->isLocale('ar') ? 'border-primary bg-primary-container text-on-primary-container' : 'border-surface-container-highest text-on-surface-variant hover:border-primary' }}"
            >
                {{ __('profile.language_ar') }}
            </a>
            <a
                href="{{ route('locale.switch', 'en') }}"
                class="flex-1 text-center py-3 rounded-xl border-2 transition-colors text-sm font-medium
                    {{ app()->isLocale('en') ? 'border-primary bg-primary-container text-on-primary-container' : 'border-surface-container-highest text-on-surface-variant hover:border-primary' }}"
            >
                {{ __('profile.language_en') }}
            </a>
        </div>
    </div>

</div>
