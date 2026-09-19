<div>
<div class="space-y-4">

    {{-- Tabs. One screen for six record types because they are one chain:
         adding a plan usually means adding its district in the same sitting. --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-2
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-1">
            @foreach ($this::TABS as $key)
                <button wire:click="selectTab('{{ $key }}')"
                        class="px-4 py-2 text-sm rounded-xl transition-colors
                               {{ $tab === $key
                                   ? 'bg-secondary text-white font-medium'
                                   : 'text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5' }}">
                    {{ __('reference.tabs.'.$key) }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Search + add --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end justify-between">

            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('common.search') }}
                </label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                 text-on-surface-variant dark:text-on-primary-container pointer-events-none">
                        search
                    </span>
                    <input wire:model.live.debounce.400ms="search"
                           type="text"
                           placeholder="{{ __('reference.search.'.$tab) }}"
                           class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                                  bg-surface-container dark:bg-[#252b3b]
                                  border border-outline-variant dark:border-white/10
                                  text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none
                                  focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            @can('reference.create')
                <button wire:click="openCreate"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium
                               bg-secondary text-white hover:brightness-110 transition-all shrink-0">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    {{ __('reference.create.'.$tab) }}
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
                        @foreach ($headers as $header)
                            <th class="text-start px-4 py-3 font-semibold
                                       text-on-surface-variant dark:text-on-primary-container">
                                {{ $header }}
                            </th>
                        @endforeach
                        <th class="text-start px-4 py-3 font-semibold
                                   text-on-surface-variant dark:text-on-primary-container">
                            {{ __('reference.dependents.'.$tab) }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                            @foreach ($row['cells'] as $cell)
                                <td class="px-4 py-3 {{ $loop->first
                                        ? 'font-medium text-on-surface dark:text-white'
                                        : 'text-on-surface-variant dark:text-on-primary-container' }}">
                                    {{ $cell }}
                                </td>
                            @endforeach

                            {{-- The number that decides whether this row can be deleted. --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium data-tabular
                                             {{ $row['dependents'] > 0
                                                 ? 'bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90'
                                                 : 'bg-surface-container text-on-surface-variant dark:bg-white/5 dark:text-on-primary-container' }}">
                                    {{ $row['dependents'] }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-end">
                                    @can('reference.edit')
                                        <button wire:click="openEdit({{ $row['id'] }})"
                                                class="p-1.5 rounded-lg text-on-surface-variant dark:text-on-primary-container
                                                       hover:bg-primary/10 hover:text-primary transition-colors"
                                                title="{{ __('common.edit') }}">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </button>
                                    @endcan
                                    @can('reference.delete')
                                        <button wire:click="confirmDelete({{ $row['id'] }})"
                                                class="p-1.5 rounded-lg text-on-surface-variant dark:text-on-primary-container
                                                       hover:bg-error/10 hover:text-error transition-colors"
                                                title="{{ __('common.delete') }}">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    @endcan
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($headers) + 2 }}" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3
                                            text-on-surface-variant dark:text-on-primary-container">
                                    <span class="material-symbols-outlined text-[48px] opacity-30">database</span>
                                    <p class="text-sm">
                                        {{ $search !== '' ? __('common.no_results') : __('reference.empty.'.$tab) }}
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($paginator->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>

</div>{{-- end space-y-4 --}}

    {{-- Create / Edit modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-md bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-4 outline-none">

                <h2 class="text-base font-semibold text-on-surface dark:text-white">
                    {{ __(($editing ? 'reference.edit.' : 'reference.create.').$tab) }}
                </h2>

                <form wire:submit="save" class="space-y-4">

                    @if ($tab === 'plans')
                        <x-form.input name="planForm.planNo"
                                      :label="__('reference.plan_no')"
                                      class="data-tabular" ltr required />

                        <x-form.select name="planForm.districtId"
                                       :label="__('reference.district')"
                                       :options="$parentOptions"
                                       :hint="__('reference.district_hint')" />

                    @elseif ($tab === 'districts')
                        <x-form.input name="districtForm.nameAr" :label="__('reference.name_ar')" required />
                        <x-form.input name="districtForm.nameEn" :label="__('reference.name_en')" ltr />

                        <x-form.select name="districtForm.cityId"
                                       :label="__('reference.city')"
                                       :options="$parentOptions" required />

                    @elseif ($tab === 'cities')
                        <x-form.input name="cityForm.nameAr" :label="__('reference.name_ar')" required />
                        <x-form.input name="cityForm.nameEn" :label="__('reference.name_en')" ltr />

                        <x-form.select name="cityForm.regionId"
                                       :label="__('reference.region')"
                                       :options="$parentOptions" required />

                    @elseif ($tab === 'regions')
                        <x-form.input name="regionForm.nameAr" :label="__('reference.name_ar')" required />
                        <x-form.input name="regionForm.nameEn" :label="__('reference.name_en')" ltr />

                        <x-form.select name="regionForm.countryId"
                                       :label="__('reference.country')"
                                       :options="$parentOptions" required />

                    @elseif ($tab === 'countries')
                        <x-form.input name="countryForm.nameAr" :label="__('reference.name_ar')" required />
                        <x-form.input name="countryForm.nameEn" :label="__('reference.name_en')" ltr />
                        <x-form.input name="countryForm.isoCode"
                                      :label="__('reference.iso_code')"
                                      class="data-tabular" ltr />

                    @elseif ($tab === 'offices')
                        <x-form.input name="officeForm.name" :label="__('reference.office_name')" required />
                        <x-form.input name="officeForm.licenseNo"
                                      :label="__('reference.license_no')"
                                      class="data-tabular" ltr />
                        <x-form.input name="officeForm.phone"
                                      :label="__('reference.phone')"
                                      type="tel" class="data-tabular" ltr />
                        <x-form.input name="officeForm.email"
                                      :label="__('reference.email')"
                                      type="email" ltr />
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal"
                                class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                       text-on-surface-variant dark:text-on-primary-container
                                       hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            {{ __('common.cancel') }}
                        </button>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl
                                       bg-secondary text-white hover:brightness-110 transition-all
                                       disabled:opacity-60">
                            <span wire:loading wire:target="save"
                                  class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            {{ __('common.save') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif

    {{-- Delete dialog. Two faces: the record is either free to go or it is
         still referenced, in which case the dialog says by how many rows
         instead of offering a button that would only fail. --}}
    @if ($showDeleteConfirm)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="cancelDelete"></div>

            <div class="relative z-10 w-full max-w-sm bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-4">

                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-[28px] text-error shrink-0">
                        {{ $deleteBlockedBy > 0 ? 'link_off' : 'warning' }}
                    </span>
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-on-surface dark:text-white">
                            {{ $deleteBlockedBy > 0 ? __('reference.delete_blocked_title') : __('reference.delete_title') }}
                        </p>
                        <p class="text-sm text-on-surface-variant dark:text-on-primary-container leading-relaxed">
                            {{ $deleteBlockedBy > 0
                                ? $this->blockedMessage($deleteBlockedBy)
                                : __('common.confirm_delete') }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="cancelDelete"
                            class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                                   text-on-surface-variant dark:text-on-primary-container
                                   hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                        {{ $deleteBlockedBy > 0 ? __('reference.close') : __('common.cancel') }}
                    </button>

                    @if ($deleteBlockedBy === 0)
                        <button wire:click="delete"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 text-sm font-medium rounded-xl bg-error text-white
                                       hover:brightness-110 transition-all disabled:opacity-60">
                            {{ __('common.delete') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

</div>{{-- end root --}}
