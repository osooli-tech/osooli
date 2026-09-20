<div>
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" wire:click="cancel"></div>

            <div class="relative w-full max-w-lg bg-surface dark:bg-[#1b2030]
                        rounded-2xl shadow-xl border border-outline-variant dark:border-white/10
                        max-h-[90vh] overflow-y-auto">

                <div class="px-6 pt-5 pb-3 border-b border-outline-variant dark:border-white/10">
                    <h2 class="text-base font-semibold text-on-surface dark:text-white">
                        {{ $form->ownerId === null ? __('owners.create_title') : __('owners.edit_title') }}
                    </h2>
                </div>

                <form wire:submit="save" class="px-6 py-5 space-y-4">

                    @if ($conflict !== '')
                        {{-- The optimistic lock, not a validation failure: the record
                             moved under the form. Shown at the top because nothing in
                             the fields below is wrong. --}}
                        <div class="rounded-xl border border-error/40 bg-error/5 px-4 py-3">
                            <p class="text-sm text-error">{{ $conflict }}</p>
                        </div>
                    @endif

                    <x-form.input name="form.name"
                                  :label="__('owners.name')"
                                  required />

                    <x-form.input name="form.nationalId"
                                  :label="__('owners.national_id')"
                                  :hint="__('owners.national_id_hint')"
                                  ltr />

                    <x-form.input name="form.phone"
                                  type="tel"
                                  :label="__('owners.phone')"
                                  :hint="__('owners.phone_hint')"
                                  ltr />

                    <x-form.input name="form.email"
                                  type="email"
                                  :label="__('owners.email')"
                                  ltr />

                    <x-form.input name="form.whatsapp"
                                  type="tel"
                                  :label="__('owners.whatsapp')"
                                  :hint="__('owners.phone_hint')"
                                  ltr />

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-5 py-2 rounded-xl text-sm font-medium
                                       bg-primary text-white hover:bg-primary/90
                                       disabled:opacity-60 transition">
                            <span wire:loading.remove wire:target="save">{{ __('common.save') }}</span>
                            <span wire:loading wire:target="save">{{ __('common.save') }}…</span>
                        </button>

                        <button type="button" wire:click="cancel"
                                class="px-5 py-2 rounded-xl text-sm font-medium
                                       border border-outline-variant dark:border-white/10
                                       text-on-surface dark:text-white
                                       hover:bg-surface-container dark:hover:bg-white/5 transition">
                            {{ __('common.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
