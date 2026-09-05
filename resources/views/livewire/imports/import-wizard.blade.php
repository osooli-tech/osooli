<div>
    @php
        use App\Enums\ImportKind;
        use App\Enums\ImportStatus;

        $status = $currentBatch?->status;
        $isDocuments = $currentBatch?->kind === ImportKind::Documents;
    @endphp

    {{-- 1. No batch yet — pick a kind and a file. The upload itself is driven
         by window.uploadImport(); Livewire only learns the uuid at the end. --}}
    @if ($currentBatch === null)
        <div x-data="{ kind: 'gdb', busy: false, progress: 0, error: null }"
             class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6
                    border border-outline-variant dark:border-white/10 shadow-sm">

            <label class="block mb-2 text-sm font-medium text-on-surface dark:text-white">
                {{ __('imports.kind.label') }}
            </label>
            <select x-model="kind"
                    class="mb-4 w-full sm:max-w-sm px-3 py-2 text-sm rounded-xl
                           bg-surface-container dark:bg-[#252b3b]
                           border border-outline-variant dark:border-white/10
                           text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/40">
                <option value="gdb">{{ __('imports.kind.gdb') }}</option>
                <option value="documents">{{ __('imports.kind.documents') }}</option>
            </select>

            <label class="block mb-2 text-sm font-medium text-on-surface dark:text-white">
                {{ __('imports.choose_file') }}
            </label>
            <input type="file" accept=".zip,.geojson,.json" x-ref="file"
                   class="mb-5 block w-full text-sm text-on-surface-variant dark:text-on-primary-container
                          file:me-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                          file:text-sm file:font-medium file:bg-secondary/10 file:text-secondary
                          dark:file:bg-secondary/20 dark:file:text-white/90">

            <button type="button"
                    :disabled="busy"
                    @click="
                        busy = true; error = null;
                        try {
                            const uuid = await window.uploadImport($refs.file.files[0], kind, {
                                onProgress: p => progress = Math.round(p * 100),
                                messages: {
                                    stuckResync: @js(__('imports.errors.stuck_resync')),
                                    invalidResponse: @js(__('imports.errors.unexpected_response')),
                                },
                            });
                            $wire.set('batchUuid', uuid);
                        } catch (e) { error = e.message } finally { busy = false }
                    "
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                           text-sm font-semibold bg-secondary text-white
                           hover:brightness-110 disabled:opacity-50 transition-all">
                <span x-show="!busy" class="inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">upload</span>
                    {{ __('imports.upload') }}
                </span>
                <span x-show="busy" x-text="`{{ __('imports.uploading') }} ${progress}%`"></span>
            </button>

            <p x-show="error" x-cloak x-text="error"
               class="mt-3 text-sm text-error"></p>
        </div>

    {{-- 2. A job is in flight — poll until it lands. --}}
    @elseif (in_array($status, [ImportStatus::Uploaded, ImportStatus::Analyzing, ImportStatus::Committing], true))
        <div wire:poll.2s
             class="flex items-center gap-3 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6
                    border border-outline-variant dark:border-white/10 shadow-sm">
            <span class="material-symbols-outlined animate-spin text-primary">progress_activity</span>
            <span class="text-sm text-on-surface dark:text-white">
                {{ $status === ImportStatus::Committing ? __('imports.committing') : __('imports.analyzing') }}
            </span>
        </div>

    {{-- 3. Analysed — show what a commit would do, then wait for a human. --}}
    @elseif ($status === ImportStatus::Previewed)
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6
                    border border-outline-variant dark:border-white/10 shadow-sm">

            <h2 class="mb-4 text-lg font-bold text-on-surface dark:text-white">{{ __('imports.preview.title') }}</h2>

            <dl class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('imports.preview.total') }}</dt>
                    <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->preview['total_items'] }}</dd>
                </div>
                <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">
                        {{ $isDocuments ? __('imports.preview.will_create_links') : __('imports.preview.will_create') }}
                    </dt>
                    <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->preview['will_create'] }}</dd>
                </div>
                <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('imports.preview.will_update') }}</dt>
                    <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->preview['will_update'] }}</dd>
                </div>
                <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                    <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">
                        {{ $isDocuments ? __('imports.preview.unmatched_files') : __('imports.preview.unmatched') }}
                    </dt>
                    <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->preview['unmatched'] }}</dd>
                </div>
            </dl>

            @if (! empty($currentBatch->preview['details']['rule']))
                <p class="mb-4 text-sm text-on-surface-variant dark:text-on-primary-container">
                    {{ __('imports.preview.rule') }}:
                    <strong class="text-on-surface dark:text-white">
                        {{ __('documents.photo_types.'.$currentBatch->preview['details']['photo_type']) }}
                    </strong>
                </p>
            @endif

            @if (! empty($currentBatch->preview['warnings']))
                <ul class="mb-5 list-disc ps-5 text-sm text-amber-700 dark:text-amber-300 space-y-1">
                    @foreach ($currentBatch->preview['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            @endif

            <div class="flex gap-3">
                @can('imports.create')
                    <button wire:click="confirm" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                                   text-sm font-semibold bg-secondary text-white
                                   hover:brightness-110 disabled:opacity-60 transition-all">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        {{ __('imports.confirm') }}
                    </button>
                @endcan
                <button wire:click="startOver"
                        class="px-4 py-2 text-sm rounded-xl
                               border border-outline-variant dark:border-white/10
                               text-on-surface-variant dark:text-on-primary-container
                               hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                    {{ __('imports.cancel') }}
                </button>
            </div>
        </div>

    {{-- 4. Terminal — report and offer another run. --}}
    @else
        <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6
                    border border-outline-variant dark:border-white/10 shadow-sm">
            @if ($status === ImportStatus::Completed)
                <h2 class="mb-4 flex items-center gap-2 text-lg font-bold text-secondary">
                    <span class="material-symbols-outlined">check_circle</span>
                    {{ __('imports.completed') }}
                </h2>

                <dl class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                        <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">
                            {{ $isDocuments ? __('imports.preview.will_create_links') : __('imports.preview.will_create') }}
                        </dt>
                        <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->result['created'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                        <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('imports.preview.will_update') }}</dt>
                        <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->result['updated'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                        <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">
                            {{ $isDocuments ? __('imports.preview.unmatched_files') : __('imports.preview.unmatched') }}
                        </dt>
                        <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->result['skipped'] }}</dd>
                    </div>
                    <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3">
                        <dt class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('imports.failed') }}</dt>
                        <dd class="text-2xl font-bold text-on-surface dark:text-white">{{ $currentBatch->result['errors'] }}</dd>
                    </div>
                </dl>

                @if (! empty($currentBatch->result['warnings']))
                    <ul class="mb-4 list-disc ps-5 text-sm text-amber-700 dark:text-amber-300 space-y-1">
                        @foreach ($currentBatch->result['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <h2 class="mb-3 flex items-center gap-2 text-lg font-bold text-error">
                    <span class="material-symbols-outlined">error</span>
                    {{ __('imports.failed') }}
                </h2>
                <p class="text-sm text-error">{{ $currentBatch->error_message }}</p>
            @endif

            <button wire:click="startOver"
                    class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-xl
                           border border-outline-variant dark:border-white/10
                           text-on-surface-variant dark:text-on-primary-container
                           hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                {{ __('imports.start_over') }}
            </button>
        </div>
    @endif

    {{-- Recent batches, always visible. --}}
    <div class="mt-8 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
        <h3 class="px-4 pt-4 pb-2 font-bold text-on-surface dark:text-white">{{ __('imports.recent.title') }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10
                                bg-surface-container dark:bg-[#1e2435]">
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('imports.recent.file') }}</th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('imports.recent.uploader') }}</th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('imports.recent.status') }}</th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('imports.recent.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($recent as $row)
                        @php
                            $chipClass = match ($row->status) {
                                ImportStatus::Completed => 'bg-secondary/15 text-secondary dark:bg-secondary/25 dark:text-emerald-300',
                                ImportStatus::Failed => 'bg-error/10 text-error dark:bg-error/20 dark:text-red-300',
                                ImportStatus::Previewed => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                default => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                            };
                        @endphp
                        <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                            <td class="px-4 py-3 text-on-surface dark:text-white">{{ $row->original_filename }}</td>
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">{{ $row->user?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $chipClass }}">
                                    {{ __('imports.status.'.$row->status->value) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container">{{ $row->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-on-surface-variant dark:text-on-primary-container">
                                {{ __('imports.recent.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
