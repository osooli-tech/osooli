<div>

    {{-- ── Page header ── --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
            {{ $roles->count() }} {{ trans_choice('settings.permissions_count', $roles->count()) }}
        </p>
        <button wire:click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                       text-sm font-semibold bg-secondary text-white
                       hover:brightness-110 transition-all">
            <span class="material-symbols-outlined text-[18px]">add</span>
            {{ __('settings.add_role') }}
        </button>
    </div>

    {{-- ── Roles grid — fixed h-[360px] cards, footer always visible (§0-D) ── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

        @foreach ($roles as $role)
            @php
                $permNames = $role->permissions->pluck('name');
                $permCount = $permNames->count();
                $userCount = $role->users_count ?? 0;
            @endphp

            <div class="h-[360px] flex flex-col
                        bg-surface-container-lowest dark:bg-[#1a1f2e]
                        rounded-2xl border border-outline-variant dark:border-white/10
                        shadow-sm overflow-hidden">

                <div class="h-1 bg-secondary shrink-0"></div>

                {{-- Header --}}
                <div class="px-5 pt-4 pb-3 shrink-0">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-secondary/10
                                    flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-secondary text-[20px]">
                                shield_person
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-on-surface dark:text-white text-sm ltr truncate">
                                {{ $role->name }}
                            </h3>
                            <div class="flex items-center gap-3 mt-1 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-[11px]
                                             font-medium text-secondary dark:text-secondary/80">
                                    <span class="material-symbols-outlined text-[13px]">key</span>
                                    {{ $permCount }} {{ trans_choice('settings.permissions_count', $permCount) }}
                                </span>
                                @if ($userCount > 0)
                                    <span class="inline-flex items-center gap-1 text-[11px]
                                                 text-on-surface-variant dark:text-on-primary-container">
                                        <span class="material-symbols-outlined text-[13px]">group</span>
                                        {{ $userCount }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mx-5 border-t border-outline-variant/50 dark:border-white/5 shrink-0"></div>

                {{-- Permissions (scrollable, never pushes footer off) --}}
                <div class="flex-1 overflow-y-auto min-h-0 px-5 py-3 space-y-3">
                    @forelse ($groups as $groupKey => $groupPerms)
                        @php
                            $active = collect($groupPerms)->filter(fn ($p) => $permNames->contains($p));
                        @endphp
                        @if ($active->isNotEmpty())
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest
                                          text-on-surface-variant dark:text-on-primary-container mb-1.5">
                                    {{ __('settings.groups.'.$groupKey) }}
                                </p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($active as $perm)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5
                                                     rounded-lg text-[11px] font-medium
                                                     bg-secondary/10 text-secondary
                                                     dark:bg-secondary/20 dark:text-white/80">
                                            <span class="material-symbols-outlined text-[10px]">
                                                check_small
                                            </span>
                                            {{ __('settings.perm.'.str_replace('.', '_', $perm)) }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @empty
                    @endforelse
                    @if ($permCount === 0)
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container italic">
                            {{ __('settings.no_permissions') }}
                        </p>
                    @endif
                </div>

                {{-- Footer — always visible (flex pinned to bottom) --}}
                <div class="px-5 py-3 border-t border-outline-variant dark:border-white/10
                            flex items-center gap-2 shrink-0">
                    <button wire:click="openEdit({{ $role->id }})"
                            class="flex-1 inline-flex items-center justify-center gap-1.5
                                   py-2 px-3 rounded-xl text-xs font-semibold
                                   bg-secondary text-white hover:brightness-110 transition-all">
                        <span class="material-symbols-outlined text-[14px]">tune</span>
                        {{ __('settings.edit_permissions') }}
                    </button>
                    <button wire:click="confirmDelete({{ $role->id }}, '{{ $role->name }}')"
                            class="w-9 h-9 flex items-center justify-center rounded-xl
                                   border border-outline-variant dark:border-white/10
                                   text-on-surface-variant dark:text-on-primary-container
                                   hover:bg-error/10 hover:text-error hover:border-error/30
                                   transition-colors"
                            title="{{ __('settings.delete_role') }}">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                    </button>
                </div>

            </div>
        @endforeach

    </div>

    {{-- ────────────────────────────────────────────────────────────────────
         MODALS  (§0-D: @if + fixed z-[9999], no @teleport)
         Overlay uses Headless-UI pattern: fixed→overflow-y-auto→flex min-h-full
         This guarantees correct centering regardless of RTL / sidebar position.
    ──────────────────────────────────────────────────────────────────────── --}}

    {{-- ── CREATE ROLE ── --}}
    @if ($showCreateModal)
        <div class="fixed inset-0 z-[9999] overflow-y-auto" wire:key="modal-create">
            <div class="flex min-h-full items-center justify-center p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                     wire:click="$set('showCreateModal', false)"></div>
                {{-- Panel --}}
                <div class="relative w-full max-w-sm
                            bg-surface-container-lowest dark:bg-[#1a1f2e]
                            rounded-2xl shadow-2xl border border-outline-variant dark:border-white/10
                            overflow-hidden">
                    <div class="h-1 bg-secondary"></div>
                    <div class="px-6 pt-5 pb-4 border-b border-outline-variant dark:border-white/10">
                        <h2 class="text-base font-semibold text-on-surface dark:text-white">
                            {{ __('settings.create_title') }}
                        </h2>
                    </div>
                    <form wire:submit="createRole" class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-on-surface dark:text-white mb-1.5">
                                {{ __('settings.role_name') }}
                                <span class="text-error ms-0.5">*</span>
                            </label>
                            <input wire:model="newRoleName"
                                   type="text"
                                   autocomplete="off"
                                   placeholder="e.g. viewer"
                                   class="w-full px-3 py-2.5 text-sm ltr rounded-xl
                                          bg-surface-container dark:bg-[#252b3b]
                                          border border-outline-variant dark:border-white/10
                                          text-on-surface dark:text-white
                                          focus:outline-none focus:ring-2 focus:ring-secondary/40
                                          @error('newRoleName') border-error ring-1 ring-error/30 @enderror" />
                            @error('newRoleName')
                                <p class="mt-1.5 text-xs text-error">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex justify-end gap-3 pt-1">
                            <button type="button"
                                    wire:click="$set('showCreateModal', false)"
                                    class="px-4 py-2 text-sm rounded-xl
                                           border border-outline-variant dark:border-white/10
                                           text-on-surface-variant dark:text-on-primary-container
                                           hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                                {{ __('settings.cancel') }}
                            </button>
                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="px-5 py-2 text-sm font-semibold rounded-xl
                                           bg-secondary text-white hover:brightness-110
                                           disabled:opacity-60 transition-all">
                                {{ __('settings.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- ── EDIT PERMISSIONS ── --}}
    @if ($showEditModal)
        <div class="fixed inset-0 z-[9999] overflow-y-auto" wire:key="modal-edit">
            <div class="flex min-h-full items-center justify-center p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                     wire:click="$set('showEditModal', false)"></div>
                {{-- Panel --}}
                <div class="relative w-full max-w-lg
                            bg-surface-container-lowest dark:bg-[#1a1f2e]
                            rounded-2xl shadow-2xl border border-outline-variant dark:border-white/10
                            overflow-hidden flex flex-col"
                     style="max-height: min(90vh, 600px);">

                    <div class="h-1 bg-secondary shrink-0"></div>

                    {{-- Header --}}
                    <div class="px-6 pt-5 pb-4 border-b border-outline-variant dark:border-white/10 shrink-0">
                        <h2 class="text-base font-semibold text-on-surface dark:text-white">
                            {{ __('settings.edit_title', ['role' => $editingRoleName]) }}
                        </h2>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5">
                            {{ collect($permissionChecks)->filter()->count() }}
                            {{ trans_choice('settings.permissions_count', collect($permissionChecks)->filter()->count()) }}
                            {{ __('settings.selected') }}
                        </p>
                    </div>

                    {{--
                        Permissions list — scrollable body.
                        Each row uses dir="ltr" so the checkbox is ALWAYS on the
                        left of the label text, regardless of page RTL direction.
                        This avoids RTL flex reordering that hides the checkbox
                        behind the sidebar or off-screen.
                    --}}
                    <div class="flex-1 overflow-y-auto min-h-0 px-6 py-4">

                        @foreach ($groups as $groupKey => $groupPerms)
                            <div class="{{ $loop->first ? '' : 'mt-5' }}">
                                {{-- Category heading --}}
                                <p class="text-[11px] font-bold uppercase tracking-widest
                                          text-on-surface-variant dark:text-on-primary-container mb-2"
                                   dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
                                    {{ __('settings.groups.'.$groupKey) }}
                                </p>

                                {{-- Permission rows --}}
                                @foreach ($groupPerms as $perm)
                                    @php $safeKey = str_replace('.', '__', $perm) @endphp
                                    <label dir="ltr"
                                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl
                                                  cursor-pointer select-none mb-1 transition-colors
                                                  hover:bg-surface-container dark:hover:bg-white/5">

                                        {{-- Checkbox — explicit inline styles so no Tailwind purge / token issue --}}
                                        <input type="checkbox"
                                               wire:model="permissionChecks.{{ $safeKey }}"
                                               style="width:18px;height:18px;min-width:18px;cursor:pointer;
                                                      accent-color:#006c4e;border-radius:4px;" />

                                        {{-- Human-readable label --}}
                                        <span style="flex:1;font-size:0.875rem;
                                                     color:var(--color-on-surface, #1a1a1a);"
                                              class="dark:!text-white">
                                            {{ __('settings.perm.'.str_replace('.', '_', $perm)) }}
                                        </span>

                                    </label>
                                @endforeach

                                @if (! $loop->last)
                                    <hr class="my-3 border-outline-variant dark:border-white/10">
                                @endif
                            </div>
                        @endforeach

                    </div>

                    {{-- Footer --}}
                    <div class="px-6 py-4 border-t border-outline-variant dark:border-white/10
                                flex justify-end gap-3 shrink-0">
                        <button wire:click="$set('showEditModal', false)"
                                class="px-4 py-2 text-sm rounded-xl
                                       border border-outline-variant dark:border-white/10
                                       text-on-surface-variant dark:text-on-primary-container
                                       hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            {{ __('settings.cancel') }}
                        </button>
                        <button wire:click="savePermissions"
                                wire:loading.attr="disabled"
                                wire:target="savePermissions"
                                class="inline-flex items-center gap-2 px-5 py-2
                                       text-sm font-semibold rounded-xl bg-secondary text-white
                                       hover:brightness-110 disabled:opacity-60 transition-all">
                            <span wire:loading wire:target="savePermissions"
                                  class="material-symbols-outlined text-[15px] animate-spin">
                                progress_activity
                            </span>
                            {{ __('settings.save') }}
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

    {{-- ── DELETE CONFIRM ── --}}
    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-[9999] overflow-y-auto" wire:key="modal-delete">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"
                     wire:click="cancelDelete"></div>
                <div class="relative w-full max-w-sm
                            bg-surface-container-lowest dark:bg-[#1a1f2e]
                            rounded-2xl shadow-2xl border border-outline-variant dark:border-white/10
                            overflow-hidden">
                    <div class="h-1 bg-error shrink-0"></div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-[26px] text-error shrink-0">
                                warning
                            </span>
                            <p class="text-sm text-on-surface dark:text-white leading-relaxed">
                                {{ __('settings.delete_confirm', ['role' => $deletingRoleName]) }}
                            </p>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button wire:click="cancelDelete"
                                    class="px-4 py-2 text-sm rounded-xl
                                           border border-outline-variant dark:border-white/10
                                           text-on-surface-variant dark:text-on-primary-container
                                           hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                                {{ __('settings.cancel') }}
                            </button>
                            <button wire:click="deleteRole"
                                    wire:loading.attr="disabled"
                                    class="px-4 py-2 text-sm font-semibold rounded-xl
                                           bg-error text-white hover:brightness-110
                                           disabled:opacity-60 transition-all">
                                {{ __('settings.confirm_delete_btn') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
