<div class="space-y-4" @if ($busy) wire:poll.2s @endif>

    @php
        $card = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 shadow-sm';
        $btn = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-secondary text-white hover:brightness-110 transition-all disabled:opacity-50';
        $ghost = 'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm border border-outline-variant dark:border-white/10 text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5';
        $state = $run['state'] ?? null;
        $counts = $run['counts'] ?? [];
        $statusStyles = [
            'new' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
            'changed' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            'same' => 'bg-surface-container text-on-surface-variant dark:bg-white/5 dark:text-on-primary-container',
            'decision' => 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300',
            'error' => 'bg-error-container text-on-error-container',
        ];
        $describe = fn (array $entry): string => __('imports_center.codes.'.$entry['code'], [
            'detail' => $entry['detail'] ?? '',
            'field' => isset($entry['field']) ? __('imports_center.fields.'.$entry['field']) : '',
            'of' => isset($entry['of']) ? ($entry['of'] + 1) : '',
        ]);
        $field = fn (string $name): string => __('imports_center.fields.'.$name);
        $show = fn (mixed $v): string => $v === null || $v === '' ? '—' : (is_float($v) ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : (string) $v);
    @endphp

    {{-- ── Upload ── --}}
    <div class="{{ $card }}">
        <h1 class="text-lg font-bold text-on-surface dark:text-white">{{ __('imports_center.title') }}</h1>
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container mt-1">{{ __('imports_center.subtitle') }}</p>

        <form wire:submit="analyse" class="mt-4 flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[260px]">
                <input type="file" wire:model="upload" accept=".geojson,.json,.zip"
                       class="block w-full text-sm text-on-surface dark:text-white file:me-3 file:px-4 file:py-2 file:rounded-xl file:border-0
                              file:bg-secondary/10 file:text-secondary file:font-medium">
                <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('imports_center.upload_hint') }}</p>
                @error('upload') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="{{ $btn }}" wire:loading.attr="disabled" wire:target="upload,analyse" @disabled($busy)>
                <span class="material-symbols-outlined text-[18px]" wire:loading.class="animate-spin" wire:target="upload,analyse">upload_file</span>
                {{ __('imports_center.analyse') }}
            </button>
        </form>

        <ul class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-xs text-on-surface-variant dark:text-on-primary-container list-disc ps-5">
            @foreach (__('imports_center.rules') as $rule)
                <li>{{ $rule }}</li>
            @endforeach
        </ul>
    </div>

    @if ($following && $run !== [])
        <div class="{{ $card }} space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-on-surface dark:text-white">{{ $run['file'] ?? '' }}</h2>
                    <p class="text-xs text-on-surface-variant dark:text-on-primary-container">
                        {{ __('imports_center.states.'.$state) }}
                        @if (! empty($run['header']['exported_at']))
                            · {{ __('imports_center.exported_at', ['date' => \Illuminate\Support\Carbon::parse($run['header']['exported_at'])->format('Y/m/d')]) }}
                        @endif
                    </p>
                </div>
                <button wire:click="close" class="{{ $ghost }}">{{ __('imports_center.close') }}</button>
            </div>

            @if ($busy)
                <div class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-4 text-sm flex items-center gap-3">
                    <span class="material-symbols-outlined animate-spin text-secondary">progress_activity</span>
                    {{ __('imports_center.working.'.$state, ['done' => number_format((int) ($run['done'] ?? 0)), 'total' => number_format((int) ($counts['total'] ?? 0))]) }}
                </div>
            @endif

            @if (in_array($state, ['failed', 'apply_failed'], true) || ! empty($run['error']))
                <p class="rounded-xl bg-error-container text-on-error-container p-3 text-sm">
                    {{ __('imports_center.failed') }}:
                    @php $errorKey = 'imports_center.file_errors.'.explode(':', (string) ($run['error'] ?? ''))[0]; @endphp
                    {{ \Illuminate\Support\Facades\Lang::has($errorKey) ? __($errorKey) : ($run['error'] ?? '') }}
                </p>
            @endif

            @if ($counts !== [] && ! in_array($state, ['analysing', 'failed'], true))
                {{-- Summary: each count filters the list below. --}}
                <div class="flex flex-wrap gap-2">
                    @foreach (['all' => $counts['total'] ?? 0, 'new' => $counts['new'] ?? 0, 'changed' => $counts['changed'] ?? 0, 'decision' => $counts['decision'] ?? 0, 'error' => $counts['error'] ?? 0, 'same' => $counts['same'] ?? 0] as $status => $n)
                        <button wire:click="showOnly('{{ $status }}')"
                                class="px-3 py-1.5 rounded-xl text-sm {{ $status === 'all' ? 'bg-surface-container dark:bg-white/10' : $statusStyles[$status] }}
                                       {{ $show === $status ? 'ring-2 ring-secondary' : '' }}">
                            {{ __('imports_center.status.'.$status) }}: <strong class="data-tabular">{{ number_format($n) }}</strong>
                        </button>
                    @endforeach
                    <span class="px-3 py-1.5 text-sm text-on-surface-variant dark:text-on-primary-container">
                        ⚠ {{ __('imports_center.warnings_count', ['count' => number_format($counts['warnings'] ?? 0)]) }}
                    </span>
                </div>

                {{-- Decisions a person has to make before anything is written. --}}
                @if ($state === 'analysed' && ! empty($run['decisions_needed']))
                    <div class="rounded-2xl border-2 border-orange-300 dark:border-orange-500/40 p-4 space-y-4">
                        <h3 class="font-semibold text-on-surface dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-orange-600">help</span>
                            {{ __('imports_center.decisions_title', ['count' => count($run['decisions_needed'])]) }}
                        </h3>

                        @foreach ($run['decisions_needed'] as $key => $decision)
                            @php $hash = md5($key); @endphp
                            <div wire:key="decision-{{ $hash }}" class="rounded-xl bg-surface-container dark:bg-[#252b3b] p-3 text-sm">
                                @if ($decision['type'] === 'owner')
                                    <p class="mb-2 text-on-surface dark:text-white">
                                        {{ __('imports_center.owner_question') }}
                                        <strong>{{ $decision['owner']['name'] ?? '—' }}</strong>
                                        <span dir="ltr" class="text-on-surface-variant">({{ $decision['owner']['national_id'] ?? '—' }} · {{ $decision['owner']['phone'] ?? '—' }})</span>
                                    </p>
                                    <div class="space-y-1">
                                        @foreach ($decision['candidates'] as $candidate)
                                            <label class="flex items-start gap-2 cursor-pointer">
                                                <input type="radio" wire:model="choices.{{ $hash }}" value="{{ $candidate['id'] }}" class="mt-1 text-secondary">
                                                <span>
                                                    {{ __('imports_center.same_as') }} <strong>{{ $candidate['label'] }}</strong>
                                                    <span class="text-xs text-on-surface-variant">
                                                        ({{ collect($candidate['reasons'])->map(fn ($r) => __('imports_center.reasons.'.$r))->implode('، ') }})
                                                    </span>
                                                </span>
                                            </label>
                                        @endforeach
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" wire:model="choices.{{ $hash }}" value="new" class="text-secondary">
                                            {{ __('imports_center.new_owner') }}
                                        </label>
                                    </div>
                                @else
                                    <p class="mb-2 text-on-surface dark:text-white">
                                        {{ __('imports_center.district_question', ['district' => $decision['name'], 'city' => $decision['city']]) }}
                                    </p>
                                    <div class="space-y-1">
                                        @foreach ($decision['suggestions'] as $suggestion)
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" wire:model="choices.{{ $hash }}" value="{{ $suggestion['id'] }}" class="text-secondary">
                                                {{ __('imports_center.use_district', ['name' => $suggestion['label']]) }}
                                            </label>
                                        @endforeach
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="radio" wire:model="choices.{{ $hash }}" value="create" class="text-secondary">
                                            {{ __('imports_center.create_district', ['name' => $decision['name']]) }}
                                        </label>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- The analysed records. --}}
                <div class="divide-y divide-outline-variant dark:divide-white/10 rounded-xl border border-outline-variant dark:border-white/10">
                    @forelse ($items as $item)
                        @php $excludedHere = in_array($item['index'], $excluded, true); @endphp
                        <div wire:key="item-{{ $item['index'] }}" class="p-3 text-sm {{ $excludedHere ? 'opacity-50' : '' }}">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="text-xs text-on-surface-variant data-tabular w-10">#{{ $item['index'] + 1 }}</span>
                                <span class="px-2 py-0.5 rounded-full text-xs {{ $statusStyles[$item['status']] }}">{{ __('imports_center.status.'.$item['status']) }}</span>
                                <button wire:click="$set('open', {{ $open === $item['index'] ? 'null' : $item['index'] }})" class="font-medium text-on-surface dark:text-white hover:underline" dir="auto">
                                    {{ __('imports_center.deed') }} {{ $item['label'] }}
                                    <span class="text-xs text-on-surface-variant">· {{ $item['parcel']['geo_id'] ?? '' }}</span>
                                </button>
                                @if ($item['warnings'] !== [])
                                    <span class="text-xs text-amber-700 dark:text-amber-300">⚠ {{ count($item['warnings']) }}</span>
                                @endif
                                @if ($state === 'analysed' && in_array($item['status'], ['new', 'changed', 'decision'], true))
                                    <label class="ms-auto flex items-center gap-1 text-xs cursor-pointer text-on-surface-variant">
                                        <input type="checkbox" wire:click="toggleExcluded({{ $item['index'] }})" @checked($excludedHere) class="rounded text-secondary">
                                        {{ __('imports_center.exclude') }}
                                    </label>
                                @endif
                            </div>

                            @if ($open === $item['index'])
                                <div class="mt-3 ms-10 space-y-3">
                                    @foreach ($item['errors'] as $error)
                                        <p class="text-error">✖ {{ $describe($error) }}</p>
                                    @endforeach
                                    @foreach ($item['warnings'] as $warning)
                                        <p class="text-amber-700 dark:text-amber-300">⚠ {{ $describe($warning) }}</p>
                                    @endforeach

                                    @php
                                        $sections = [
                                            'deed' => ($item['deed'] ?? []),
                                            'parcel' => ($item['parcel'] ?? []),
                                            'boundary' => ($item['boundary'] ?? []),
                                        ];
                                    @endphp
                                    @foreach ($sections as $section => $part)
                                        @if (($part['action'] ?? null) === 'update' && ! empty($part['changes']))
                                            <div>
                                                <p class="font-medium text-on-surface dark:text-white">{{ __('imports_center.sections.'.$section) }} — {{ __('imports_center.actions.update') }}</p>
                                                <table class="mt-1 text-xs">
                                                    @foreach ($part['changes'] as $name => [$old, $new])
                                                        <tr>
                                                            <td class="pe-4 py-0.5 text-on-surface-variant">{{ $field($name) }}</td>
                                                            <td class="pe-2 line-through text-error/80" dir="auto">{{ $show($old) }}</td>
                                                            <td class="text-secondary font-medium" dir="auto">← {{ $show($new) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </table>
                                            </div>
                                        @elseif (($part['action'] ?? null) === 'create')
                                            <p><span class="text-emerald-700 dark:text-emerald-300">＋</span> {{ __('imports_center.sections.'.$section) }} — {{ __('imports_center.actions.create') }}</p>
                                        @endif
                                    @endforeach

                                    @if (! empty($item['parcel']['geometry_change']))
                                        <p>{{ __('imports_center.geometry_change', ['old' => number_format((float) $item['parcel']['geometry_change']['old']), 'new' => number_format((float) $item['parcel']['geometry_change']['new'])]) }}</p>
                                    @endif

                                    @if (($item['plan']['action'] ?? null) === 'create')
                                        <p>＋ {{ __('imports_center.new_plan', ['plan' => $item['plan']['plan_no']]) }}</p>
                                    @endif

                                    @foreach ($item['owners'] ?? [] as $owner)
                                        <div class="text-xs">
                                            <span class="font-medium text-on-surface dark:text-white">{{ $owner['name'] ?? '—' }}</span>
                                            <span dir="ltr" class="text-on-surface-variant">{{ $owner['national_id'] ?? '' }}</span>
                                            — {{ __('imports_center.owner_actions.'.$owner['action']) }}
                                            @if ($owner['share'] !== null) · {{ $show($owner['share']) }}% @endif
                                            @foreach ($owner['changes'] ?? [] as $name => [$old, $new])
                                                <span class="ms-2">{{ $field($name) }}: <span class="line-through">{{ $show($old) }}</span> ← {{ $show($new) }}</span>
                                            @endforeach
                                        </div>
                                    @endforeach

                                    @foreach ($item['survey'] ?? [] as $decision)
                                        <p class="text-xs">{{ __('imports_center.sections.survey') }} — {{ __('imports_center.actions.'.$decision['action']) }}</p>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="p-4 text-sm text-on-surface-variant">{{ __('imports_center.nothing_here') }}</p>
                    @endforelse
                </div>

                @if ($pages > 1)
                    <div class="flex items-center justify-center gap-3 text-sm">
                        <button wire:click="$set('page', {{ max(1, $page - 1) }})" class="{{ $ghost }}" @disabled($page <= 1)>‹</button>
                        <span class="data-tabular">{{ $page }} / {{ $pages }}</span>
                        <button wire:click="$set('page', {{ min($pages, $page + 1) }})" class="{{ $ghost }}" @disabled($page >= $pages)>›</button>
                    </div>
                @endif

                {{-- Apply --}}
                @if ($state === 'analysed')
                    <div class="pt-4 border-t border-outline-variant dark:border-white/10 space-y-3">
                        <label class="flex items-start gap-2 text-sm cursor-pointer text-on-surface dark:text-white">
                            <input type="checkbox" wire:model="confirmed" class="mt-0.5 rounded text-secondary">
                            <span>{{ __('imports_center.confirm_text', [
                                'new' => number_format($counts['new'] ?? 0),
                                'changed' => number_format($counts['changed'] ?? 0),
                                'excluded' => count($excluded),
                            ]) }}</span>
                        </label>
                        @error('confirmed') <p class="text-xs text-error">{{ $message }}</p> @enderror
                        @if (($counts['error'] ?? 0) > 0)
                            <p class="text-xs text-error">{{ __('imports_center.errors_skipped', ['count' => number_format($counts['error'])]) }}</p>
                        @endif
                        <button wire:click="apply" class="{{ $btn }}" wire:loading.attr="disabled">
                            <span class="material-symbols-outlined text-[18px]">done_all</span>
                            {{ __('imports_center.apply') }}
                        </button>
                    </div>
                @endif

                @if ($state === 'applied')
                    <div class="pt-4 border-t border-outline-variant dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-secondary font-semibold">
                            {{ __('imports_center.applied', collect($run['result'] ?? [])->map(fn ($n) => number_format($n))->all()) }}
                        </p>
                        <button wire:click="undo" wire:confirm="{{ __('imports_center.undo_confirm') }}" class="{{ $ghost }}">
                            <span class="material-symbols-outlined text-[18px]">undo</span>
                            {{ __('imports_center.undo') }}
                        </button>
                    </div>
                @endif

                @if ($state === 'undone')
                    <div class="pt-4 border-t border-outline-variant dark:border-white/10 text-sm">
                        <p class="text-on-surface dark:text-white">{{ __('imports_center.undone', ['restored' => $run['undo_result']['restored'] ?? 0, 'deleted' => $run['undo_result']['deleted'] ?? 0]) }}</p>
                        @if (! empty($run['undo_result']['kept']))
                            <p class="mt-1 text-amber-700 dark:text-amber-300">{{ __('imports_center.undo_kept', ['count' => count($run['undo_result']['kept'])]) }}</p>
                            <p class="mt-1 text-xs text-on-surface-variant" dir="ltr">{{ implode(', ', array_slice($run['undo_result']['kept'], 0, 30)) }}</p>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- ── History ── --}}
    <div class="{{ $card }}">
        <h2 class="text-sm font-semibold text-on-surface dark:text-white mb-3">{{ __('imports_center.history', ['days' => \App\Support\Import\ImportRuns::KEEP_DAYS]) }}</h2>
        @if ($history === [])
            <p class="text-sm text-on-surface-variant">{{ __('imports_center.history_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-on-surface-variant border-b border-outline-variant dark:border-white/10">
                            <th class="py-2 text-start">{{ __('imports_center.col_date') }}</th>
                            <th class="py-2 text-start">{{ __('imports_center.col_file') }}</th>
                            <th class="py-2 text-start">{{ __('imports_center.col_user') }}</th>
                            <th class="py-2 text-start">{{ __('imports_center.col_state') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                        @foreach ($history as $entry)
                            <tr wire:key="import-{{ $entry['id'] }}">
                                <td class="py-2 whitespace-nowrap" dir="ltr">{{ \Illuminate\Support\Carbon::parse($entry['created_at'])->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</td>
                                <td class="py-2" dir="auto">{{ $entry['file'] ?? '—' }}</td>
                                <td class="py-2">{{ $entry['user_name'] ?? '—' }}</td>
                                <td class="py-2">{{ __('imports_center.states.'.$entry['state']) }}</td>
                                <td class="py-2 text-end">
                                    <button wire:click="follow('{{ $entry['id'] }}')" class="text-secondary hover:underline">{{ __('imports_center.open') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
