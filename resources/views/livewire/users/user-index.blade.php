<div>
<div class="space-y-4">

    {{-- Search + filters bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end justify-between">

            <div class="flex flex-wrap gap-3 items-end flex-1">
                {{-- Search --}}
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                        {{ __('users.search_placeholder') }}
                    </label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                     text-on-surface-variant dark:text-on-primary-container pointer-events-none">
                            search
                        </span>
                        <input wire:model.live.debounce.400ms="search"
                               type="text"
                               placeholder="{{ __('users.search_placeholder') }}"
                               class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                                      bg-surface-container dark:bg-[#252b3b]
                                      border border-outline-variant dark:border-white/10
                                      text-on-surface dark:text-white
                                      placeholder:text-on-surface-variant focus:outline-none
                                      focus:ring-2 focus:ring-primary/40" />
                    </div>
                </div>

                {{-- Role filter --}}
                <div class="min-w-[160px]">
                    <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                        {{ __('users.role') }}
                    </label>
                    <select wire:model.live="filterRole"
                            class="w-full px-3 py-2 text-sm rounded-xl
                                   bg-surface-container dark:bg-[#252b3b]
                                   border border-outline-variant dark:border-white/10
                                   text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                        <option value="">{{ __('users.all_roles') }}</option>
                        @foreach ($availableRoles as $roleName)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Clear --}}
                @if ($search !== '' || $filterRole !== '')
                    <button wire:click="$set('search', ''); $set('filterRole', '')"
                            class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl
                                   text-error border border-error/30 hover:bg-error/10 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                        {{ __('users.clear_filters') }}
                    </button>
                @endif
            </div>

            {{-- Add button --}}
            @can('users.create')
                <button wire:click="openCreate"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium
                               bg-secondary text-white hover:brightness-110 transition-all shrink-0">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    {{ __('users.add_user') }}
                </button>
            @endcan
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10
                                bg-surface-container dark:bg-[#1e2435]">
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('users.name') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('users.email') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('users.role') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('users.status') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('users.last_login') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($users as $user)
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                            {{-- Name + avatar --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/15 dark:bg-primary/25
                                                flex items-center justify-center shrink-0
                                                text-primary dark:text-white text-sm font-bold">
                                        {{ mb_substr($user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-on-surface dark:text-white">{{ $user->name }}</p>
                                        @if ($user->phone)
                                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular">
                                                {{ $user->phone }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">
                                {{ $user->email }}
                            </td>

                            {{-- Role --}}
                            <td class="px-4 py-3">
                                @php $roleName = $user->roles->first()?->name @endphp
                                @if ($roleName)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                 bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90">
                                        {{ $roleName }}
                                    </span>
                                @else
                                    <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                @endif
                            </td>

                            {{-- Active toggle --}}
                            <td class="px-4 py-3">
                                @can('users.edit')
                                    <button wire:click="toggleActive({{ $user->id }})"
                                            wire:loading.attr="disabled"
                                            title="{{ $user->is_active ? __('users.active') : __('users.inactive') }}"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium
                                                   transition-colors cursor-pointer
                                                   {{ $user->is_active
                                                       ? 'bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-green-300 hover:bg-error/10 hover:text-error'
                                                       : 'bg-error/10 text-error hover:bg-secondary/10 hover:text-secondary' }}">
                                        <span class="material-symbols-outlined text-[13px]">
                                            {{ $user->is_active ? 'check_circle' : 'cancel' }}
                                        </span>
                                        {{ $user->is_active ? __('users.active') : __('users.inactive') }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium
                                                 {{ $user->is_active
                                                     ? 'bg-secondary/10 text-secondary dark:bg-secondary/20'
                                                     : 'bg-error/10 text-error' }}">
                                        <span class="material-symbols-outlined text-[13px]">
                                            {{ $user->is_active ? 'check_circle' : 'cancel' }}
                                        </span>
                                        {{ $user->is_active ? __('users.active') : __('users.inactive') }}
                                    </span>
                                @endcan
                            </td>

                            {{-- Last login --}}
                            <td class="px-4 py-3 text-xs text-on-surface-variant dark:text-on-primary-container data-tabular">
                                {{ $user->last_login_at?->diffForHumans() ?? __('users.never') }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    @can('users.edit')
                                        <button wire:click="openEdit({{ $user->id }})"
                                                class="p-1.5 rounded-lg text-on-surface-variant dark:text-on-primary-container
                                                       hover:bg-primary/10 hover:text-primary transition-colors"
                                                title="{{ __('users.edit') }}">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </button>
                                    @endcan
                                    @can('users.delete')
                                        @if ($user->id !== auth()->id())
                                            <button wire:click="confirmDelete({{ $user->id }})"
                                                    class="p-1.5 rounded-lg text-on-surface-variant dark:text-on-primary-container
                                                           hover:bg-error/10 hover:text-error transition-colors"
                                                    title="{{ __('users.delete') }}">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3
                                            text-on-surface-variant dark:text-on-primary-container">
                                    <span class="material-symbols-outlined text-[48px] opacity-30">manage_accounts</span>
                                    <p class="text-sm">{{ __('users.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $users->links() }}
            </div>
        @endif
    </div>

</div>{{-- end space-y-4 --}}

    {{-- Create / Edit Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="$set('showModal', false)"></div>

            {{-- Panel --}}
            <div data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-md bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-4 outline-none">

                <h2 class="text-base font-semibold text-on-surface dark:text-white">
                    {{ $editingId ? __('users.edit_title') : __('users.create_title') }}
                </h2>

                <form wire:submit="save" class="space-y-4">

                    {{-- Name --}}
                    <div>
                        <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('users.name') }} <span class="text-error">*</span>
                        </label>
                        <input wire:model="formName" type="text" autocomplete="off"
                               class="w-full px-3 py-2 text-sm rounded-xl
                                      bg-surface-container dark:bg-[#252b3b]
                                      border border-outline-variant dark:border-white/10
                                      text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40
                                      @error('formName') border-error ring-1 ring-error/40 @enderror" />
                        @error('formName')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('users.email') }} <span class="text-error">*</span>
                        </label>
                        <input wire:model="formEmail" type="email" autocomplete="off"
                               class="w-full px-3 py-2 text-sm rounded-xl ltr
                                      bg-surface-container dark:bg-[#252b3b]
                                      border border-outline-variant dark:border-white/10
                                      text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40
                                      @error('formEmail') border-error ring-1 ring-error/40 @enderror" />
                        @error('formEmail')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('users.phone') }}
                        </label>
                        <input wire:model="formPhone" type="tel" autocomplete="off"
                               class="w-full px-3 py-2 text-sm rounded-xl data-tabular
                                      bg-surface-container dark:bg-[#252b3b]
                                      border border-outline-variant dark:border-white/10
                                      text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40
                                      @error('formPhone') border-error ring-1 ring-error/40 @enderror" />
                        @error('formPhone')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('users.password') }}
                            @if ($editingId)
                                <span class="text-on-surface-variant dark:text-on-primary-container font-normal">
                                    — {{ __('users.password_hint') }}
                                </span>
                            @else
                                <span class="text-error">*</span>
                            @endif
                        </label>
                        <input wire:model="formPassword" type="password" autocomplete="new-password"
                               class="w-full px-3 py-2 text-sm rounded-xl ltr
                                      bg-surface-container dark:bg-[#252b3b]
                                      border border-outline-variant dark:border-white/10
                                      text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40
                                      @error('formPassword') border-error ring-1 ring-error/40 @enderror" />
                        @error('formPassword')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Role --}}
                    <div>
                        <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                            {{ __('users.role') }} <span class="text-error">*</span>
                        </label>
                        <select wire:model="formRole"
                                class="w-full px-3 py-2 text-sm rounded-xl
                                       bg-surface-container dark:bg-[#252b3b]
                                       border border-outline-variant dark:border-white/10
                                       text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40
                                       @error('formRole') border-error ring-1 ring-error/40 @enderror">
                            <option value="">{{ __('users.role_select') }}</option>
                            @foreach ($availableRoles as $roleName)
                                <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                        @error('formRole')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Buttons --}}
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showModal', false)"
                                class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                       text-on-surface-variant dark:text-on-primary-container
                                       hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            {{ __('users.cancel') }}
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl
                                       bg-secondary text-white hover:brightness-110 transition-all
                                       disabled:opacity-60">
                            <span wire:loading wire:target="save"
                                  class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            {{ __('users.save') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif

    {{-- Delete confirm --}}
    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                 wire:click="cancelDelete"></div>
            <div class="relative z-10 w-full max-w-sm bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[28px] text-error shrink-0">warning</span>
                    <p class="text-sm text-on-surface dark:text-white leading-relaxed">
                        {{ __('users.delete_confirm') }}
                    </p>
                </div>
                <div class="flex justify-end gap-3">
                    <button wire:click="cancelDelete"
                            class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                   text-on-surface-variant dark:text-on-primary-container
                                   hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                        {{ __('users.cancel') }}
                    </button>
                    <button wire:click="deleteUser"
                            class="px-4 py-2 text-sm font-medium rounded-xl bg-error text-white
                                   hover:brightness-110 transition-all">
                        {{ __('users.confirm_delete_btn') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>{{-- end root --}}
