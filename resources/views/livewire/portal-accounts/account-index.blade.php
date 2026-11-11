@php
    $cardCls = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl border border-outline-variant dark:border-white/10 shadow-sm';
    $inputCls = 'w-full px-3 py-2 text-sm rounded-xl bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10 text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40';
    $btnCls = 'inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors';
@endphp
<div class="space-y-5">
    <p class="text-sm text-on-surface-variant dark:text-on-primary-container max-w-3xl">{{ __('portal_accounts.intro') }}</p>

    <div class="flex items-center gap-2" role="tablist">
        @foreach (['all' => __('portal_accounts.tab_all'), 'review' => __('portal_accounts.tab_review')] as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    class="px-4 py-2 text-sm rounded-xl {{ $tab === $key ? 'bg-primary text-on-primary' : 'bg-surface-container dark:bg-white/5' }}">
                {{ $label }}
                @if ($key === 'review' && $pending->isNotEmpty())
                    <span class="ms-1 px-1.5 rounded-full bg-error text-on-error text-[11px]">{{ $pending->count() }}</span>
                @endif
            </button>
        @endforeach
    </div>

    @if ($tab === 'review')
        <section class="{{ $cardCls }} p-5 space-y-3">
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('portal_accounts.pending_explain') }}</p>
            @forelse ($pending as $grant)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl p-3 border border-outline-variant dark:border-white/10">
                    <div class="text-sm">
                        <span class="font-semibold">{{ $grant->owner?->name }}</span>
                        <span class="text-on-surface-variant dark:text-on-primary-container"> ← </span>
                        <span dir="ltr" class="data-tabular">{{ $grant->account?->phone }}</span>
                        <span class="ms-2 text-xs text-on-surface-variant dark:text-on-primary-container">{{ $grant->relation->label() }} · {{ __('portal_accounts.source.'.$grant->source->value) }}</span>
                    </div>
                    @if ($canManage)
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="approve({{ $grant->id }})" class="{{ $btnCls }} bg-secondary/10 text-secondary hover:bg-secondary/20">{{ __('portal_accounts.approve') }}</button>
                            <button type="button" wire:click="reject({{ $grant->id }})" wire:confirm="{{ __('portal_accounts.reject') }}؟" class="{{ $btnCls }} bg-error/10 text-error hover:bg-error/20">{{ __('portal_accounts.reject') }}</button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container py-4 text-center">{{ __('portal_accounts.pending_none') }}</p>
            @endforelse
        </section>
    @else
        @if ($canManage)
            <section class="{{ $cardCls }} p-5" x-data="{ open: false }">
                <button type="button" @click="open = ! open" class="flex items-center gap-2 font-semibold">
                    <span class="material-symbols-outlined text-[20px]">person_add</span>{{ __('portal_accounts.create') }}
                </button>
                <form wire:submit="create" x-show="open" x-cloak class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <label for="new-phone" class="block text-xs font-medium mb-1">{{ __('portal_accounts.phone') }}</label>
                        <input id="new-phone" type="tel" dir="ltr" wire:model="newPhone" placeholder="05XXXXXXXX" class="{{ $inputCls }}">
                        @error('newPhone') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="new-name" class="block text-xs font-medium mb-1">{{ __('portal_accounts.name') }}</label>
                        <input id="new-name" type="text" wire:model="newName" class="{{ $inputCls }}">
                        <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-1">{{ __('portal_accounts.name_hint') }}</p>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="owner-search" class="block text-xs font-medium mb-1">{{ __('portal_accounts.choose_owners') }}</label>
                        <input id="owner-search" type="search" wire:model.live.debounce.300ms="ownerSearch" placeholder="{{ __('portal_accounts.search_owners') }}" class="{{ $inputCls }}">
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            @foreach ($ownerChoices as $choice)
                                <label class="flex items-center gap-2 text-sm rounded-lg px-2 py-1.5 border border-outline-variant dark:border-white/10">
                                    <input type="checkbox" value="{{ $choice->id }}" wire:model.live="newOwnerIds">{{ $choice->name }}
                                </label>
                            @endforeach
                        </div>
                        @error('newOwnerIds') <p class="text-xs text-error mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="new-relation" class="block text-xs font-medium mb-1">{{ __('portal_accounts.role') }}</label>
                        <select id="new-relation" wire:model="newRelation" class="{{ $inputCls }}">
                            <option value="delegate">{{ __('portal_accounts.relation.delegate') }}</option>
                            <option value="self">{{ __('portal_accounts.relation.self') }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm self-end pb-2">
                        <input type="checkbox" wire:model="newCanManage">{{ __('portal_accounts.can_manage') }}
                    </label>
                    <div class="lg:col-span-2">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-xl bg-primary text-on-primary hover:opacity-90">{{ __('portal_accounts.create_submit') }}</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="{{ $cardCls }} overflow-hidden">
            <div class="p-4 border-b border-outline-variant dark:border-white/10">
                <label for="account-search" class="sr-only">{{ __('portal_accounts.search_owners') }}</label>
                <input id="account-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('portal_accounts.name') }} / {{ __('portal_accounts.phone') }}" class="{{ $inputCls }} max-w-sm">
            </div>
            @forelse ($accounts as $account)
                @php
                    $visible = $scope === null ? $account->grants : $account->grants->whereIn('owner_id', $scope);
                    $outside = $account->grants->count() - $visible->count();
                @endphp
                <div wire:key="account-{{ $account->id }}" class="p-4 border-b border-outline-variant/60 dark:border-white/5 space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            @if (isset($renaming[$account->id]))
                                <input type="text" wire:model="renaming.{{ $account->id }}" wire:keydown.enter="rename({{ $account->id }})" aria-label="{{ __('portal_accounts.name') }}" class="{{ $inputCls }} max-w-xs">
                            @else
                                <p class="font-semibold">
                                    @if ($account->isCompany())<span class="material-symbols-outlined text-[16px] align-middle text-tertiary">domain</span>@endif
                                    {{ $account->name ?: '—' }}
                                    @if ($account->isCompany() && $account->cr_number)<span class="ms-1 text-xs font-normal text-on-surface-variant dark:text-on-primary-container">· {{ __('portal_accounts.cr_number') }} <span dir="ltr" class="data-tabular">{{ $account->cr_number }}</span></span>@endif
                                    @unless ($account->is_active)<span class="ms-2 text-xs px-2 py-0.5 rounded-full bg-error/10 text-error">{{ __('portal_accounts.inactive') }}</span>@endunless
                                </p>
                            @endif
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">
                                <span dir="ltr" class="data-tabular">{{ $account->phone }}</span> ·
                                {{ __('portal_accounts.last_login') }}: {{ $account->last_login_at?->diffForHumans() ?? __('portal_accounts.never') }}
                            </p>
                        </div>
                        @if ($canManage && $outside === 0)
                            <div class="flex flex-wrap items-center gap-2">
                                @if (isset($renaming[$account->id]))
                                    <button type="button" wire:click="rename({{ $account->id }})" class="{{ $btnCls }} bg-secondary/10 text-secondary">{{ __('portal_accounts.saved') }}</button>
                                @else
                                    <button type="button" wire:click="startRename({{ $account->id }})" class="{{ $btnCls }} bg-surface-container dark:bg-white/5">{{ __('portal_accounts.rename') }}</button>
                                @endif
                                <button type="button" wire:click="openSettings({{ $account->id }})" class="{{ $btnCls }} bg-primary/10 text-primary dark:text-white">{{ __('portal_accounts.settings') }}</button>
                                <button type="button" wire:click="signOutEverywhere({{ $account->id }})" wire:confirm="{{ __('portal_accounts.sign_out_everywhere') }}؟" class="{{ $btnCls }} bg-surface-container dark:bg-white/5">{{ __('portal_accounts.sign_out_everywhere') }}</button>
                                @if ($account->is_active)
                                    <button type="button" wire:click="deactivate({{ $account->id }})" wire:confirm="{{ __('portal_accounts.deactivate') }} {{ $account->phone }}؟" class="{{ $btnCls }} bg-error/10 text-error">{{ __('portal_accounts.deactivate') }}</button>
                                @else
                                    <button type="button" wire:click="activate({{ $account->id }})" class="{{ $btnCls }} bg-secondary/10 text-secondary">{{ __('portal_accounts.activate') }}</button>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($visible as $grant)
                            <span class="text-xs px-2.5 py-1 rounded-full {{ $grant->needs_review ? 'bg-tertiary-container/40' : 'bg-surface-container dark:bg-white/5' }}">
                                {{ $grant->owner?->name }} · {{ $grant->relation->label() }}@unless ($grant->can_manage) · {{ __('portal_accounts.view_only') }}@endunless
                            </span>
                        @endforeach
                        @if ($outside > 0)
                            <span class="text-xs px-2.5 py-1 text-on-surface-variant dark:text-on-primary-container">{{ trans_choice('portal_accounts.outside_scope', $outside, ['count' => $outside]) }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-on-surface-variant dark:text-on-primary-container p-6 text-center">{{ __('portal_accounts.none') }}</p>
            @endforelse
            <div class="p-4">{{ $accounts->links() }}</div>
        </section>
    @endif

    {{-- Who the account is, and what it may do for each owner it opens --}}
    @if ($settingsAccount = $this->settingsAccount)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50" wire:click.self="closeSettings">
            <div class="w-full max-w-3xl max-h-[90vh] flex flex-col rounded-2xl bg-surface-container-lowest dark:bg-[#1a1f2e] shadow-xl">
                <div class="px-6 py-4 border-b border-outline-variant dark:border-white/10 shrink-0">
                    <h3 class="font-semibold">{{ __('portal_accounts.settings') }} — <span dir="ltr" class="data-tabular">{{ $settingsAccount->phone }}</span></h3>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium mb-1">{{ __('portal_accounts.kind') }}</label>
                            <select wire:model.live="settingsKind" class="{{ $inputCls }}">
                                <option value="person">{{ __('portal_accounts.kind_person') }}</option>
                                <option value="company">{{ __('portal_accounts.kind_company') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1">{{ $settingsKind === 'company' ? __('portal_accounts.company_name') : __('portal_accounts.name') }}</label>
                            <input type="text" wire:model="settingsName" maxlength="150" class="{{ $inputCls }}">
                            @error('settingsName') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                        </div>
                        @if ($settingsKind === 'company')
                            <div>
                                <label class="block text-xs font-medium mb-1">{{ __('portal_accounts.cr_number') }}</label>
                                <input type="text" wire:model="settingsCr" dir="ltr" maxlength="10" inputmode="numeric" class="{{ $inputCls }} data-tabular">
                                @error('settingsCr') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold">{{ __('portal_accounts.permissions_title') }}</h4>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-3">{{ __('portal_accounts.permissions_hint') }}</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-outline-variant dark:border-white/10 text-[11px] font-bold text-on-surface-variant dark:text-on-primary-container">
                                        <th class="px-2 py-2 text-start">{{ __('portal_accounts.owner') }}</th>
                                        <th class="px-2 py-2 text-center">{{ __('portal_accounts.can_manage') }}</th>
                                        @foreach (\App\Enums\PortalPermission::cases() as $permission)
                                            <th class="px-2 py-2 text-center">{{ $permission->label() }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/40 dark:divide-white/5">
                                    @foreach ($settingsAccount->grants as $grant)
                                        <tr wire:key="perm-{{ $grant->id }}">
                                            <td class="px-2 py-2">
                                                {{ $grant->owner?->name ?? '—' }}
                                                @if ($grant->needs_review)<span class="block text-[11px] text-tertiary">{{ __('portal_accounts.tab_review') }}</span>@endif
                                            </td>
                                            <td class="px-2 py-2 text-center"><input type="checkbox" wire:model="settingsManage.{{ $grant->id }}" aria-label="{{ __('portal_accounts.can_manage') }}"></td>
                                            @foreach (\App\Enums\PortalPermission::cases() as $permission)
                                                <td class="px-2 py-2 text-center"><input type="checkbox" wire:model="settingsPerms.{{ $grant->id }}.{{ $permission->value }}" aria-label="{{ $permission->label() }}"></td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t border-outline-variant dark:border-white/10 flex items-center justify-between gap-3 shrink-0">
                    <button type="button" wire:click="closeSettings" class="{{ $btnCls }} bg-surface-container dark:bg-white/5">{{ __('portal_accounts.close') }}</button>
                    <button type="button" wire:click="saveSettings" class="{{ $btnCls }} bg-secondary text-white">{{ __('portal_accounts.save') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
