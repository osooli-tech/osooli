<div class="space-y-4">

    <form wire:submit="save"
          class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 space-y-5
                 border border-outline-variant dark:border-white/10 shadow-sm">

        <h2 class="text-base font-semibold text-on-surface dark:text-white">
            {{ __('documents.upload_title') }}
        </h2>

        {{-- Where the document is filed. The parcel is fixed when this screen
             was opened from one parcel's page, and the deed dropdown only has
             anything to offer once a parcel is chosen. --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-form.select name="form.parcelId"
                           :label="__('documents.parcel')"
                           :options="$parcelOptions"
                           :disabled="$parcelLocked"
                           wire:change="$refresh"
                           required />

            <x-form.select name="form.deedId"
                           :label="__('documents.deed')"
                           :options="$deedOptions"
                           :placeholder="$form->parcel() === null ? __('documents.choose_parcel_first') : null"
                           :hint="__('documents.deed_hint')" />

            <x-form.enum-select name="form.photoType"
                                :label="__('documents.photo_type')"
                                column="photo_type"
                                required />
        </div>

        {{-- Drop zone. The real <input> stays hidden and is driven both ways:
             clicking the zone opens the picker, dropping on it assigns the
             dropped FileList and fires `change` so wire:model picks it up. --}}
        <div x-data="{ dragging: false }"
             x-on:dragover.prevent="dragging = true"
             x-on:dragleave.prevent="dragging = false"
             x-on:drop.prevent="
                 dragging = false;
                 $refs.picker.files = $event.dataTransfer.files;
                 $refs.picker.dispatchEvent(new Event('change'));
             "
             x-on:click="$refs.picker.click()"
             role="button"
             tabindex="0"
             x-on:keydown.enter.prevent="$refs.picker.click()"
             x-on:keydown.space.prevent="$refs.picker.click()"
             :class="dragging
                 ? 'border-primary bg-primary/10'
                 : 'border-outline-variant dark:border-white/20 hover:bg-surface-container dark:hover:bg-white/5'"
             class="flex flex-col items-center justify-center gap-2 px-6 py-10 rounded-2xl cursor-pointer
                    border-2 border-dashed transition-colors text-center outline-none
                    focus:ring-2 focus:ring-primary/40">

            <span class="material-symbols-outlined text-[36px] text-on-surface-variant dark:text-on-primary-container opacity-70">
                cloud_upload
            </span>

            <p class="text-sm text-on-surface dark:text-white">{{ __('documents.drop_hint') }}</p>

            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">
                {{ __('documents.accepted_formats') }}
                &middot;
                {{ __('documents.max_size', ['size' => $maxMegabytes]) }}
                &middot;
                {{ __('documents.max_files', ['count' => $maxFiles]) }}
            </p>

            <input x-ref="picker"
                   type="file"
                   multiple
                   accept="{{ $acceptAttribute }}"
                   wire:model="form.files"
                   class="hidden" />
        </div>

        {{-- Upload progress: a scanned deed is megabytes over a field
             connection, long enough that silence reads as a broken page. --}}
        <div x-data="{ progress: 0, uploading: false }"
             x-on:livewire-upload-start="uploading = true; progress = 0"
             x-on:livewire-upload-finish="uploading = false"
             x-on:livewire-upload-error="uploading = false"
             x-on:livewire-upload-progress="progress = $event.detail.progress"
             x-show="uploading"
             x-cloak
             class="space-y-1">
            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">
                {{ __('documents.uploading') }}
            </p>
            <div class="h-1.5 w-full rounded-full bg-surface-container dark:bg-white/10 overflow-hidden">
                <div class="h-full bg-primary transition-all" :style="`width: ${progress}%`"></div>
            </div>
        </div>

        @error('form.files')
            <p class="text-xs text-error">{{ $message }}</p>
        @enderror

        {{-- Selected files, before submitting --}}
        @if ($form->files !== [])
            <div class="space-y-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide
                          text-on-surface-variant dark:text-on-primary-container">
                    {{ __('documents.selected_files') }}
                </p>

                <ul class="divide-y divide-outline-variant dark:divide-white/10
                           rounded-xl border border-outline-variant dark:border-white/10 overflow-hidden">
                    @foreach ($form->files as $index => $file)
                        <li class="flex items-center gap-3 px-3 py-2 bg-surface-container dark:bg-[#252b3b]">
                            <span class="material-symbols-outlined text-[18px] text-on-surface-variant
                                         dark:text-on-primary-container shrink-0">description</span>

                            <span class="flex-1 min-w-0 text-sm text-on-surface dark:text-white truncate">
                                {{ $file->getClientOriginalName() }}
                            </span>

                            <span class="text-xs text-on-surface-variant dark:text-on-primary-container data-tabular shrink-0">
                                {{ number_format($file->getSize() / 1024, 0) }} KB
                            </span>

                            <button type="button"
                                    wire:click="removeFile({{ $index }})"
                                    aria-label="{{ __('documents.remove_file') }}"
                                    class="shrink-0 p-1 rounded-lg text-error hover:bg-error/10 transition-colors">
                                <span class="material-symbols-outlined text-[18px]">close</span>
                            </button>
                        </li>

                        @error('form.files.'.$index)
                            <li class="px-3 py-1.5 bg-error/5">
                                <p class="text-xs text-error">{{ $message }}</p>
                            </li>
                        @enderror
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="text-xs rounded-xl px-3 py-2
                  bg-tertiary/10 text-tertiary dark:bg-tertiary/20 dark:text-white/90">
            {{ __('documents.pending_notice') }}
        </p>

        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save, form.files"
                    class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl
                           bg-secondary text-white hover:brightness-110 transition-all
                           disabled:opacity-60">
                <span wire:loading wire:target="save"
                      class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                {{ __('documents.upload') }}
            </button>
        </div>

    </form>
</div>
