<div>

    {{-- Parcel create / edit --}}
    @if ($showParcelModal)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeParcel"></div>

            <div data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-3xl max-h-[90vh] overflow-y-auto
                        bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-5 outline-none">

                <h2 class="text-base font-semibold text-on-surface dark:text-white">
                    {{ $form->parcelId ? __('parcels.edit_title') : __('parcels.create_title') }}
                </h2>

                <form wire:submit="save" class="space-y-6">

                    {{-- Identity --}}
                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container
                                   border-b border-outline-variant dark:border-white/10 pb-1.5">
                            {{ __('parcels.section_identity') }}
                        </h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.input name="form.parcelNo" :label="__('parcels.parcel_no')" />
                            <x-form.input name="form.geoId"
                                          :label="__('parcels.geo_id')"
                                          :hint="__('parcels.geo_id_hint')"
                                          required ltr />
                        </div>
                    </section>

                    {{-- Placement --}}
                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container
                                   border-b border-outline-variant dark:border-white/10 pb-1.5">
                            {{ __('parcels.section_placement') }}
                        </h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.select name="form.planId"
                                           :label="__('parcels.plan')"
                                           :options="$planOptions" />
                            <x-form.select name="form.parentParcelId"
                                           :label="__('parcels.parent_parcel')"
                                           :options="$parentParcelOptions"
                                           :hint="__('parcels.parent_parcel_hint')" />
                        </div>

                        <p class="flex items-start gap-2 text-xs text-on-surface-variant dark:text-on-primary-container/70">
                            <span class="material-symbols-outlined text-[16px] shrink-0">layers</span>
                            {{ __('parcels.geometry_note') }}
                        </p>
                    </section>

                    {{-- Classification --}}
                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container
                                   border-b border-outline-variant dark:border-white/10 pb-1.5">
                            {{ __('parcels.section_classification') }}
                        </h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.enum-select name="form.assetType"
                                                :label="__('parcels.asset_type')"
                                                column="asset_type" />
                            <x-form.enum-select name="form.landTransaction"
                                                :label="__('parcels.land_transaction')"
                                                column="land_transaction" />
                            <x-form.enum-select name="form.allocationMethod"
                                                :label="__('parcels.allocation_method')"
                                                column="allocation_method" />
                            <x-form.enum-select name="form.fallIn"
                                                :label="__('parcels.fall_in')"
                                                column="fall_in" />
                        </div>
                    </section>

                    {{-- Valuation --}}
                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container
                                   border-b border-outline-variant dark:border-white/10 pb-1.5">
                            {{ __('parcels.section_valuation') }}
                        </h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.input name="form.mPrice"
                                          :label="__('parcels.m_price')"
                                          type="number" step="0.01" min="0"
                                          class="data-tabular" ltr />
                            <x-form.input name="form.parcelPrice"
                                          :label="__('parcels.parcel_price')"
                                          type="number" step="0.01" min="0"
                                          class="data-tabular" ltr />
                        </div>
                    </section>

                    {{-- Sync provenance --}}
                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container
                                   border-b border-outline-variant dark:border-white/10 pb-1.5">
                            {{ __('parcels.section_sync') }}
                        </h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.input name="form.sourceGdbId"
                                          :label="__('parcels.source_gdb_id')"
                                          type="number" step="1" min="0"
                                          class="data-tabular" ltr />
                            <x-form.input name="form.lastSyncedAt"
                                          :label="__('parcels.last_synced_at')"
                                          type="datetime-local"
                                          class="data-tabular" ltr />
                        </div>

                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container/70">
                            {{ __('parcels.sync_hint') }}
                        </p>
                    </section>

                    {{-- Deeds: only once the parcel has an id to hang them on --}}
                    @if ($form->parcelId)
                        <section class="space-y-3">
                            <div class="flex items-center justify-between gap-3
                                        border-b border-outline-variant dark:border-white/10 pb-1.5">
                                <h3 class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container">
                                    {{ __('parcels.section_deeds') }}
                                </h3>

                                @can('deeds.create')
                                    <button type="button" wire:click="addDeed"
                                            class="flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-medium
                                                   text-primary hover:bg-primary/10 transition-colors">
                                        <span class="material-symbols-outlined text-[16px]">add</span>
                                        {{ __('parcels.add_deed') }}
                                    </button>
                                @endcan
                            </div>

                            @forelse ($deeds as $deed)
                                <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl
                                            bg-surface-container dark:bg-[#252b3b]
                                            border border-outline-variant dark:border-white/10">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-on-surface dark:text-white truncate">
                                            {{ $deed->deed_no ?: __('parcels.unset') }}
                                        </p>
                                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular">
                                            {{ $deed->deed_date_hijri ?: __('parcels.unset') }}
                                            @if ($deed->deed_status)
                                                — {{ $deed->deed_status }}
                                            @endif
                                        </p>
                                    </div>

                                    @can('deeds.edit')
                                        <button type="button" wire:click="editDeed({{ $deed->id }})"
                                                title="{{ __('parcels.edit_deed') }}"
                                                class="p-1.5 rounded-lg shrink-0
                                                       text-on-surface-variant dark:text-on-primary-container
                                                       hover:bg-primary/10 hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </button>
                                    @endcan
                                </div>
                            @empty
                                <p class="text-xs text-on-surface-variant dark:text-on-primary-container py-2">
                                    {{ __('parcels.no_deeds_yet') }}
                                </p>
                            @endforelse
                        </section>
                    @endif

                    {{-- Buttons --}}
                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeParcel"
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

    {{-- Deed create / edit --}}
    @if ($showDeedModal)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">

            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeDeed"></div>

            <div data-modal tabindex="-1"
                 class="relative z-10 w-full max-w-md max-h-[90vh] overflow-y-auto
                        bg-surface dark:bg-[#1a1f2e] rounded-2xl shadow-2xl
                        border border-outline-variant dark:border-white/10 p-6 space-y-4 outline-none">

                <h2 class="text-base font-semibold text-on-surface dark:text-white">
                    {{ $deedForm->deedId ? __('parcels.deed_edit_title') : __('parcels.deed_create_title') }}
                </h2>

                <form wire:submit="saveDeed" class="space-y-4">

                    <x-form.input name="deedForm.deedNo" :label="__('parcels.deed_no')" ltr />

                    <x-form.input name="deedForm.deedDateHijri"
                                  :label="__('parcels.deed_date')"
                                  :hint="__('parcels.deed_date_hint')"
                                  placeholder="1446-03-15"
                                  class="data-tabular" ltr />

                    <x-form.input name="deedForm.deedArea"
                                  :label="__('parcels.deed_area')"
                                  type="number" step="0.01" min="0"
                                  class="data-tabular" ltr />

                    <x-form.enum-select name="deedForm.deedStatus"
                                        :label="__('parcels.deed_status')"
                                        column="deed_status" />

                    <x-form.enum-select name="deedForm.deedClass"
                                        :label="__('parcels.deed_class')"
                                        column="deed_class" />

                    @error('deedForm.parcelId')
                        <p class="text-xs text-error">{{ $message }}</p>
                    @enderror

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeDeed"
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
                            <span wire:loading wire:target="saveDeed"
                                  class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                            {{ __('common.save') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif

</div>
