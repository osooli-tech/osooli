<div class="space-y-4" @if ($running) wire:poll.2s @endif>

    @php
        $card = 'bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-5 border border-outline-variant dark:border-white/10 shadow-sm';
        $label = 'block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1';
        $input = 'w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10 text-on-surface dark:text-white placeholder:text-on-surface-variant focus:outline-none focus:ring-2 focus:ring-primary/40';
        $sectionTitle = 'text-sm font-semibold text-on-surface dark:text-white mb-3 flex items-center gap-2';
    @endphp

    {{-- Intro --}}
    <div class="{{ $card }}">
        <h1 class="text-lg font-bold text-on-surface dark:text-white">{{ __('exports_center.title') }}</h1>
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container mt-1">{{ __('exports_center.subtitle') }}</p>
    </div>

    {{-- ── Filters ── --}}
    <div class="{{ $card }} space-y-5">

        <div>
            <h2 class="{{ $sectionTitle }}">
                <span class="material-symbols-outlined text-secondary text-[20px]">location_on</span>
                {{ __('exports_center.section_location') }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <x-form.search-select name="filters.region_id" source="regions" live
                                      :label="__('reference.region')" :value="$filters['region_id']"
                                      :placeholder="__('reference.filters.all_regions')" />
                <x-form.search-select name="filters.city_id" source="cities" parent="filters.region_id" live
                                      :label="__('reference.city')" :value="$filters['city_id']"
                                      :placeholder="__('reference.filters.all_cities')" />
                <x-form.search-select name="filters.district_id" source="districts" parent="filters.city_id" live
                                      :label="__('reference.district')" :value="$filters['district_id']"
                                      :placeholder="__('reference.filters.all_districts')" />
                <div>
                    <label class="{{ $label }}">{{ __('reference.plan_no') }}</label>
                    <input type="text" wire:model.live.debounce.500ms="filters.plan_no" class="{{ $input }}" dir="ltr">
                </div>
            </div>
        </div>

        <div>
            <h2 class="{{ $sectionTitle }}">
                <span class="material-symbols-outlined text-secondary text-[20px]">search</span>
                {{ __('exports_center.section_search') }}
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="{{ $label }}">{{ __('exports_center.deed_no') }}</label>
                    <input type="text" wire:model.live.debounce.500ms="filters.deed_no" class="{{ $input }}" dir="ltr">
                </div>
                <div>
                    <label class="{{ $label }}">{{ __('exports_center.parcel') }}</label>
                    <input type="text" wire:model.live.debounce.500ms="filters.parcel" class="{{ $input }}" dir="ltr">
                </div>
                <div>
                    <label class="{{ $label }}">{{ __('exports_center.owner') }}</label>
                    <input type="text" wire:model.live.debounce.500ms="filters.owner" class="{{ $input }}"
                           placeholder="{{ __('exports_center.owner_hint') }}">
                </div>
            </div>
        </div>

        {{-- Lists: tick any number; none ticked means all. --}}
        <div>
            <h2 class="{{ $sectionTitle }}">
                <span class="material-symbols-outlined text-secondary text-[20px]">checklist</span>
                {{ __('exports_center.section_lists') }}
                <span class="text-xs font-normal text-on-surface-variant dark:text-on-primary-container">{{ __('exports_center.lists_hint') }}</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($enums as $column => $options)
                    <fieldset class="rounded-xl border border-outline-variant dark:border-white/10 p-3">
                        <legend class="px-1 text-xs font-medium text-on-surface-variant dark:text-on-primary-container">
                            {{ __('exports_center.enums.'.$column) }}
                        </legend>
                        <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                            @foreach ($options as $option)
                                <label class="flex items-center gap-1.5 text-sm text-on-surface dark:text-white cursor-pointer">
                                    <input type="checkbox" wire:model.live="filters.{{ $column }}" value="{{ $option }}"
                                           class="rounded text-secondary focus:ring-secondary">
                                    {{ $option }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div>
                <h2 class="{{ $sectionTitle }}">
                    <span class="material-symbols-outlined text-secondary text-[20px]">straighten</span>
                    {{ __('exports_center.section_ranges') }}
                </h2>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $label }}">{{ __('exports_center.area') }} — {{ __('exports_center.from') }}</label>
                        <input type="number" min="0" step="any" wire:model.live.debounce.500ms="filters.area_min" class="{{ $input }}" dir="ltr">
                        @error('filters.area_min') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('exports_center.area') }} — {{ __('exports_center.to') }}</label>
                        <input type="number" min="0" step="any" wire:model.live.debounce.500ms="filters.area_max" class="{{ $input }}" dir="ltr">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('exports_center.price') }} — {{ __('exports_center.from') }}</label>
                        <input type="number" min="0" step="any" wire:model.live.debounce.500ms="filters.price_min" class="{{ $input }}" dir="ltr">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('exports_center.price') }} — {{ __('exports_center.to') }}</label>
                        <input type="number" min="0" step="any" wire:model.live.debounce.500ms="filters.price_max" class="{{ $input }}" dir="ltr">
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('exports_center.deed_date') }} — {{ __('exports_center.from') }}</label>
                        <input type="text" wire:model.live.debounce.500ms="filters.date_from" placeholder="1440-01-01" class="{{ $input }}" dir="ltr">
                        @error('filters.date_from') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ __('exports_center.deed_date') }} — {{ __('exports_center.to') }}</label>
                        <input type="text" wire:model.live.debounce.500ms="filters.date_to" placeholder="1447-12-29" class="{{ $input }}" dir="ltr">
                        @error('filters.date_to') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div>
                <h2 class="{{ $sectionTitle }}">
                    <span class="material-symbols-outlined text-secondary text-[20px]">rule</span>
                    {{ __('exports_center.section_presence') }}
                </h2>
                <div class="grid grid-cols-2 gap-3">
                    @foreach (['has_geometry', 'has_boundary', 'has_survey', 'has_documents'] as $key)
                        <div>
                            <label class="{{ $label }}">{{ __('exports_center.presence.'.$key) }}</label>
                            <select wire:model.live="filters.{{ $key }}" class="{{ $input }}">
                                <option value="">{{ __('exports_center.any') }}</option>
                                <option value="yes">{{ __('exports_center.yes') }}</option>
                                <option value="no">{{ __('exports_center.no') }}</option>
                            </select>
                        </div>
                    @endforeach
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm text-on-surface dark:text-white cursor-pointer">
                    <input type="checkbox" wire:model.live="filters.include_archived" class="rounded text-secondary focus:ring-secondary">
                    {{ __('exports_center.include_archived') }}
                </label>
            </div>
        </div>
    </div>

    {{-- ── What each deed carries, and go ── --}}
    <div class="{{ $card }}">
        <h2 class="{{ $sectionTitle }}">
            <span class="material-symbols-outlined text-secondary text-[20px]">dataset</span>
            {{ __('exports_center.section_groups') }}
        </h2>
        <div class="flex flex-wrap gap-x-6 gap-y-2 mb-5">
            <label class="flex items-center gap-1.5 text-sm text-on-surface-variant dark:text-on-primary-container">
                <input type="checkbox" checked disabled class="rounded text-secondary">
                {{ __('exports_center.groups.deed') }}
            </label>
            @foreach (\App\Support\Export\DeedGeoJsonExporter::GROUPS as $group)
                <label class="flex items-center gap-1.5 text-sm text-on-surface dark:text-white cursor-pointer">
                    <input type="checkbox" wire:model="groups" value="{{ $group }}" class="rounded text-secondary focus:ring-secondary">
                    {{ __('exports_center.groups.'.$group) }}
                </label>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-outline-variant dark:border-white/10">
            <div class="flex items-center gap-3">
                <span class="text-3xl font-bold text-secondary data-tabular" wire:loading.class="opacity-40">
                    {{ $count === null ? '—' : number_format($count) }}
                </span>
                <span class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('exports_center.matching') }}</span>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" wire:click="resetFilters"
                        class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10
                               text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5">
                    {{ __('exports_center.reset') }}
                </button>
                <button type="button" wire:click="export" wire:loading.attr="disabled" @disabled($running || ! $count)
                        class="flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-xl bg-secondary text-white
                               hover:brightness-110 transition-all disabled:opacity-50">
                    <span class="material-symbols-outlined text-[18px]">file_export</span>
                    {{ __('exports_center.export_geojson') }}
                </button>
            </div>
        </div>

        @if ($current)
            @php $percent = ($current['total'] ?? 0) > 0 ? (int) floor(100 * $current['done'] / $current['total']) : 0; @endphp
            <div class="mt-4 rounded-xl bg-surface-container dark:bg-[#252b3b] p-4 text-sm">
                @if ($current['state'] === 'running')
                    <div class="flex justify-between mb-2">
                        <span>{{ __('exports_center.progress', ['done' => number_format($current['done']), 'total' => number_format($current['total'])]) }}</span>
                        <span class="data-tabular">{{ $percent }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-surface dark:bg-white/10 overflow-hidden">
                        <div class="h-full bg-secondary transition-all" style="width: {{ $percent }}%"></div>
                    </div>
                @elseif ($current['state'] === 'succeeded')
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-secondary font-semibold">
                            {{ __('exports_center.done', ['count' => number_format($current['done'])]) }}
                        </span>
                        <a href="{{ route('exports.download', $current['id']) }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-secondary text-white text-sm font-medium hover:brightness-110">
                            <span class="material-symbols-outlined text-[18px]">download</span>
                            {{ __('exports_center.download') }}
                        </a>
                    </div>
                @else
                    <p class="text-error">{{ __('exports_center.failed') }}: <span dir="ltr">{{ $current['error'] }}</span></p>
                @endif
            </div>
        @endif
    </div>

    {{-- ── History ── --}}
    <div class="{{ $card }}">
        <h2 class="{{ $sectionTitle }}">
            <span class="material-symbols-outlined text-secondary text-[20px]">history</span>
            {{ __('exports_center.history', ['days' => \App\Support\Export\ExportRuns::KEEP_DAYS]) }}
        </h2>

        @if ($history === [])
            <p class="text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('exports_center.history_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-on-surface-variant dark:text-on-primary-container border-b border-outline-variant dark:border-white/10">
                            <th class="py-2 text-start font-semibold">{{ __('exports_center.col_date') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('exports_center.col_user') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('exports_center.col_filters') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('exports_center.col_count') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('exports_center.col_size') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                        @foreach ($history as $run)
                            <tr wire:key="run-{{ $run['id'] }}" class="align-top">
                                <td class="py-2 whitespace-nowrap" dir="ltr">{{ \Illuminate\Support\Carbon::parse($run['started_at'])->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</td>
                                <td class="py-2">{{ $run['user_name'] ?? '—' }}</td>
                                <td class="py-2 text-xs text-on-surface-variant dark:text-on-primary-container max-w-md">
                                    @forelse ($run['filters'] ?? [] as $key => $value)
                                        <span class="inline-block me-2">{{ __('exports_center.filter_names.'.$key) }}: {{ is_array($value) ? implode('، ', $value) : ($value === true ? '✓' : $value) }}</span>
                                    @empty
                                        {{ __('exports_center.all_deeds') }}
                                    @endforelse
                                </td>
                                <td class="py-2 data-tabular">{{ number_format((int) ($run['done'] ?? 0)) }}</td>
                                <td class="py-2 data-tabular" dir="ltr">{{ \App\Support\Export\ExportRuns::humanSize((int) ($run['bytes'] ?? 0)) }}</td>
                                <td class="py-2 text-end">
                                    @if ($run['state'] === 'succeeded')
                                        <a href="{{ route('exports.download', $run['id']) }}" class="inline-flex items-center gap-1 text-secondary hover:underline">
                                            <span class="material-symbols-outlined text-[18px]">download</span>
                                            {{ __('exports_center.download') }}
                                        </a>
                                    @elseif ($run['state'] === 'running')
                                        <span class="text-on-surface-variant">{{ __('exports_center.running') }}</span>
                                    @else
                                        <span class="text-error">{{ __('exports_center.failed') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
