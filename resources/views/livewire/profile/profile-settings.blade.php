<div>

    @php
        /** @var \App\Models\User $user */
        $user = auth()->user();
    @endphp

    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-on-surface">{{ __('profile.title') }}</h1>
        <p class="text-sm text-on-surface-variant mt-1">{{ __('profile.subtitle') }}</p>
    </div>

    {{-- ─── Summary Card ───────────────────────────────────────────────── --}}
    <div class="bg-primary rounded-2xl p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5">

            {{-- Avatar --}}
            <div class="w-20 h-20 rounded-full bg-secondary flex items-center justify-center text-3xl font-bold text-on-secondary shrink-0 ring-4 ring-white/20">
                {{ mb_substr($user->name, 0, 1) }}
            </div>

            {{-- Info --}}
            <div class="flex-1 text-center sm:text-start">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 mb-2">
                    <h2 class="text-xl font-bold text-on-primary">{{ $user->name }}</h2>
                    @if($user->getRoleNames()->isNotEmpty())
                        <span class="inline-block px-3 py-0.5 rounded-full bg-secondary-container text-on-secondary-container text-xs font-semibold">
                            {{ $user->getRoleNames()->first() }}
                        </span>
                    @endif
                </div>
                <p class="text-on-primary/80 text-sm" dir="ltr">{{ $user->email }}</p>
                <p class="text-on-primary/50 text-xs mt-1">
                    {{ __('profile.member_since') }}: {{ $user->created_at?->format('Y/m/d') }}
                </p>
            </div>

        </div>
    </div>

    {{-- ─── Two cards ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

        {{-- ─── Card 1: Personal Info ──────────────────────────────── --}}
        <div class="bg-white rounded-2xl shadow-sm border border-surface-container p-6 flex flex-col">

            <h2 class="text-base font-semibold text-on-surface mb-5 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-secondary-container flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-secondary text-base">person</span>
                </span>
                {{ __('profile.section_info') }}
            </h2>

            <div class="space-y-4 flex-1">

                {{-- Name --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1.5">
                        {{ __('profile.name') }}
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="{{ __('profile.name_placeholder') }}"
                        class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest bg-surface text-on-surface placeholder-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary text-sm transition-all"
                    >
                    @error('name')
                        <p class="text-error text-xs mt-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Email (read-only) --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1.5">
                        {{ __('profile.email') }}
                    </label>
                    <div class="relative">
                        <input
                            type="email"
                            value="{{ $user->email }}"
                            readonly
                            dir="ltr"
                            class="w-full px-4 py-2.5 pe-10 rounded-xl border border-surface-container-highest bg-surface-container text-on-surface-variant cursor-not-allowed text-sm"
                        >
                        <span class="absolute top-1/2 -translate-y-1/2 end-3 material-symbols-outlined text-on-surface-variant/50 text-sm">lock</span>
                    </div>
                </div>

                {{-- Phone --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1.5">
                        {{ __('profile.phone') }}
                    </label>
                    <input
                        type="text"
                        wire:model="phone"
                        placeholder="{{ __('profile.phone_placeholder') }}"
                        dir="ltr"
                        class="w-full px-4 py-2.5 rounded-xl border border-surface-container-highest bg-surface text-on-surface placeholder-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary text-sm transition-all"
                    >
                    @error('phone')
                        <p class="text-error text-xs mt-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

            </div>

            <div class="mt-6 pt-5 border-t border-surface-container flex justify-end">
                <button
                    wire:click="saveInfo"
                    wire:loading.attr="disabled"
                    wire:target="saveInfo"
                    class="inline-flex items-center gap-2 bg-secondary text-on-secondary px-6 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
                >
                    <span class="material-symbols-outlined text-base" wire:loading.class="animate-spin" wire:target="saveInfo">save</span>
                    <span wire:loading.remove wire:target="saveInfo">{{ __('profile.save_info') }}</span>
                    <span wire:loading wire:target="saveInfo">...</span>
                </button>
            </div>

        </div>

        {{-- ─── Card 2: Change Password ─────────────────────────────── --}}
        <div
            class="bg-white rounded-2xl shadow-sm border border-surface-container p-6 flex flex-col"
            x-data="{ showCurrent: false, showNew: false, showConfirm: false }"
        >

            <h2 class="text-base font-semibold text-on-surface mb-5 flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-secondary-container flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-secondary text-base">lock</span>
                </span>
                {{ __('profile.section_password') }}
            </h2>

            <div class="space-y-4 flex-1">

                {{-- Current password --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1.5">
                        {{ __('profile.current_password') }}
                    </label>
                    <div class="relative">
                        <input
                            :type="showCurrent ? 'text' : 'password'"
                            wire:model="currentPassword"
                            class="w-full px-4 py-2.5 pe-11 rounded-xl border border-surface-container-highest bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary text-sm transition-all"
                        >
                        <button
                            type="button"
                            @click="showCurrent = !showCurrent"
                            class="absolute top-1/2 -translate-y-1/2 end-3 text-on-surface-variant hover:text-secondary transition-colors"
                            tabindex="-1"
                        >
                            <span class="material-symbols-outlined text-lg leading-none" x-text="showCurrent ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                    @error('currentPassword')
                        <p class="text-error text-xs mt-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- New password --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1.5">
                        {{ __('profile.new_password') }}
                    </label>
                    <div class="relative">
                        <input
                            :type="showNew ? 'text' : 'password'"
                            wire:model="newPassword"
                            class="w-full px-4 py-2.5 pe-11 rounded-xl border border-surface-container-highest bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary text-sm transition-all"
                        >
                        <button
                            type="button"
                            @click="showNew = !showNew"
                            class="absolute top-1/2 -translate-y-1/2 end-3 text-on-surface-variant hover:text-secondary transition-colors"
                            tabindex="-1"
                        >
                            <span class="material-symbols-outlined text-lg leading-none" x-text="showNew ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                    @error('newPassword')
                        <p class="text-error text-xs mt-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Confirm password --}}
                <div>
                    <label class="block text-xs font-semibold text-on-surface-variant uppercase tracking-wide mb-1.5">
                        {{ __('profile.confirm_password') }}
                    </label>
                    <div class="relative">
                        <input
                            :type="showConfirm ? 'text' : 'password'"
                            wire:model="confirmPassword"
                            class="w-full px-4 py-2.5 pe-11 rounded-xl border border-surface-container-highest bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary text-sm transition-all"
                        >
                        <button
                            type="button"
                            @click="showConfirm = !showConfirm"
                            class="absolute top-1/2 -translate-y-1/2 end-3 text-on-surface-variant hover:text-secondary transition-colors"
                            tabindex="-1"
                        >
                            <span class="material-symbols-outlined text-lg leading-none" x-text="showConfirm ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                    @error('confirmPassword')
                        <p class="text-error text-xs mt-1 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">error</span>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

            </div>

            <div class="mt-6 pt-5 border-t border-surface-container flex justify-end">
                <button
                    wire:click="changePassword"
                    wire:loading.attr="disabled"
                    wire:target="changePassword"
                    class="inline-flex items-center gap-2 bg-secondary text-on-secondary px-6 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-60 disabled:cursor-not-allowed shadow-sm"
                >
                    <span class="material-symbols-outlined text-base">key</span>
                    <span wire:loading.remove wire:target="changePassword">{{ __('profile.save_password') }}</span>
                    <span wire:loading wire:target="changePassword">...</span>
                </button>
            </div>

        </div>

    </div>

</div>
