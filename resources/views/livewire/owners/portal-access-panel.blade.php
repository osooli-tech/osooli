<div class="space-y-2">
    <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container uppercase tracking-wide flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">phonelink_lock</span>{{ __('portal_accounts.panel_title') }}
    </p>

    @forelse ($grants as $grant)
        <div wire:key="grant-{{ $grant->id }}" class="flex flex-wrap items-center justify-between gap-2 rounded-xl px-3 py-2 bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10">
            <div class="text-sm">
                <span dir="ltr" class="data-tabular font-medium">{{ $grant->account?->phone }}</span>
                @if ($grant->account?->name)<span class="text-on-surface-variant dark:text-on-primary-container"> · {{ $grant->account->name }}</span>@endif
                <span class="ms-1 text-xs text-on-surface-variant dark:text-on-primary-container">
                    {{ $grant->relation->label() }} · {{ $grant->can_manage ? __('portal_accounts.manage') : __('portal_accounts.view_only') }}
                    @if ($grant->needs_review) · {{ __('portal_accounts.tab_review') }}@endif
                    @unless ($grant->account?->is_active) · {{ __('portal_accounts.inactive') }}@endunless
                </span>
            </div>
            @if ($mayManage)
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="toggleManage({{ $grant->id }})" class="px-2 py-1 text-xs rounded-lg bg-surface-container dark:bg-white/5">
                        {{ $grant->can_manage ? __('portal_accounts.view_only') : __('portal_accounts.manage') }}
                    </button>
                    <button type="button" wire:click="revoke({{ $grant->id }})" wire:confirm="{{ __('portal_accounts.revoke') }} {{ $grant->account?->phone }}؟" class="px-2 py-1 text-xs rounded-lg bg-error/10 text-error">
                        {{ __('portal_accounts.revoke') }}
                    </button>
                </div>
            @endif
        </div>
    @empty
        <p class="text-xs text-on-surface-variant dark:text-on-primary-container py-1">{{ __('portal_accounts.panel_none') }}</p>
    @endforelse

    @if ($mayManage)
        <form wire:submit="addPhone" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="panel-phone-{{ $ownerId }}" class="block text-[11px] mb-0.5">{{ __('portal_accounts.phone') }}</label>
                <input id="panel-phone-{{ $ownerId }}" type="tel" dir="ltr" wire:model="phone" placeholder="05XXXXXXXX"
                       class="w-36 px-2.5 py-1.5 text-xs rounded-lg bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10">
            </div>
            <div>
                <label for="panel-relation-{{ $ownerId }}" class="block text-[11px] mb-0.5">{{ __('portal_accounts.role') }}</label>
                <select id="panel-relation-{{ $ownerId }}" wire:model="relation" class="px-2.5 py-1.5 text-xs rounded-lg bg-surface dark:bg-[#1a2435] border border-outline-variant dark:border-white/10">
                    <option value="delegate">{{ __('portal_accounts.relation.delegate') }}</option>
                    <option value="self">{{ __('portal_accounts.relation.self') }}</option>
                </select>
            </div>
            <label class="flex items-center gap-1 text-xs pb-1.5"><input type="checkbox" wire:model="canManage">{{ __('portal_accounts.manage') }}</label>
            <button type="submit" class="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg bg-secondary/10 text-secondary hover:bg-secondary/20">
                <span class="material-symbols-outlined text-[14px]">add</span>{{ __('portal_accounts.panel_add') }}
            </button>
        </form>
        @error('phone') <p class="text-xs text-error">{{ $message }}</p> @enderror
    @endif
</div>
