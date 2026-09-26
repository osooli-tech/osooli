{{-- The full analysis of an uploaded geodatabase, and every decision the
     import needs, each pre-set to the analysis's suggestion. Nothing is
     written until "Confirm import"; the choices are saved with the batch. --}}
@php
    $a = $gdb['analysis'];
    $total = (int) ($currentBatch->preview['total_items'] ?? 0);
    $card = 'rounded-xl border border-outline-variant dark:border-white/10 p-4';
    $h3 = 'mb-3 flex items-center gap-2 font-bold text-on-surface dark:text-white';
    $muted = 'text-xs text-on-surface-variant dark:text-on-primary-container';
    $th = 'text-start px-3 py-2 font-semibold text-xs text-on-surface-variant dark:text-on-primary-container';
    $td = 'px-3 py-2 text-on-surface dark:text-white';
    $select = 'rounded-lg border border-outline-variant dark:border-white/10 bg-surface-container-lowest dark:bg-[#252b3b] px-2 py-1 text-sm text-on-surface dark:text-white';
    $radio = 'flex items-start gap-2 text-sm text-on-surface dark:text-white cursor-pointer';
    $roleUsed = fn (string $role): bool => collect($options['layers'] ?? [])->contains('role', $role);
@endphp

<div class="mb-6 space-y-5">

    {{-- 1. Layers --}}
    <section class="{{ $card }}">
        <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">layers</span>{{ __('imports.gdb.layers') }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-container dark:bg-white/5">
                    <tr>
                        <th class="{{ $th }}">{{ __('imports.gdb.layer') }}</th>
                        <th class="{{ $th }}">{{ __('imports.gdb.geometry') }}</th>
                        <th class="{{ $th }}">{{ __('imports.gdb.features') }}</th>
                        <th class="{{ $th }}">{{ __('imports.gdb.crs') }}</th>
                        <th class="{{ $th }}">{{ __('imports.gdb.fields') }}</th>
                        <th class="{{ $th }}">{{ __('imports.gdb.import_as') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @foreach ($gdb['layers'] as $layer)
                        @php $i = collect($options['layers'] ?? [])->search(fn ($c) => ($c['name'] ?? null) === $layer['name']); @endphp
                        <tr wire:key="layer-{{ $loop->index }}">
                            <td class="{{ $td }} font-medium" dir="ltr">{{ $layer['name'] }}</td>
                            <td class="{{ $td }}" dir="ltr">{{ $layer['geometry'] ?: __('imports.gdb.no_geometry') }}</td>
                            <td class="{{ $td }} data-tabular">{{ number_format($layer['count']) }}</td>
                            <td class="{{ $td }} text-xs" dir="ltr">{{ $layer['crs'] ?? '—' }}</td>
                            <td class="{{ $td }} data-tabular">{{ count($layer['fields']) }}</td>
                            <td class="{{ $td }}">
                                @if ($i !== false)
                                    <select wire:model.live="options.layers.{{ $i }}.role" class="{{ $select }}">
                                        @foreach (\App\Services\Import\GdbImporter::ROLES as $role)
                                            <option value="{{ $role }}">{{ __('imports.gdb.roles.'.$role) }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                        </tr>

                        @php
                            $choice = $i === false ? null : ($options['layers'][$i] ?? null);
                            $similar = $gdb['similar'][$loop->index] ?? [];
                        @endphp

                        {{-- A custom layer: new, or added to / replacing one on the map. --}}
                        @if (($choice['role'] ?? null) === 'custom')
                            <tr wire:key="layer-custom-{{ $loop->index }}" class="bg-surface-container/50 dark:bg-white/5">
                                <td colspan="6" class="px-3 py-2">
                                    <div class="flex flex-wrap items-center gap-2 text-sm text-on-surface dark:text-white">
                                        <span class="material-symbols-outlined text-[18px] text-secondary">subdirectory_arrow_left</span>
                                        <select wire:model.live="options.layers.{{ $i }}.target" class="{{ $select }}">
                                            <option value="">{{ __('imports.gdb.custom.new') }}</option>
                                            @foreach ($customLayers as $existing)
                                                <option value="{{ $existing->id }}">{{ __('imports.gdb.custom.existing', ['name' => $existing->name, 'count' => number_format($existing->feature_count)]) }}</option>
                                            @endforeach
                                        </select>
                                        @if (empty($choice['target']))
                                            <label class="flex items-center gap-2">
                                                {{ __('imports.gdb.custom.name') }}
                                                <input type="text" wire:model.blur="options.layers.{{ $i }}.new_name" maxlength="140"
                                                       class="{{ $select }} w-56" dir="auto">
                                            </label>
                                        @else
                                            <select wire:model.live="options.layers.{{ $i }}.mode" class="{{ $select }}">
                                                <option value="replace">{{ __('imports.gdb.custom.replace') }}</option>
                                                <option value="append">{{ __('imports.gdb.custom.append') }}</option>
                                            </select>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif

                        {{-- A name close to a layer on record: ask before a near-duplicate is made. --}}
                        @php
                            $relevant = collect($similar)->reject(fn ($m) =>
                                ($m['kind'] === 'custom' && ($choice['role'] ?? null) === 'custom' && (int) ($choice['target'] ?? 0) === (int) $m['id'])
                                || ($m['kind'] === 'built_in' && ($choice['role'] ?? null) === $m['role']));
                        @endphp
                        @if ($relevant->isNotEmpty() && ($choice['role'] ?? 'ignore') !== 'ignore')
                            <tr wire:key="layer-similar-{{ $loop->index }}">
                                <td colspan="6" class="px-3 pb-3">
                                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-sm text-amber-800 dark:text-amber-200">
                                        <p class="mb-2 flex items-center gap-1.5 font-medium">
                                            <span class="material-symbols-outlined text-[18px]">warning</span>
                                            {{ __('imports.gdb.similar.title', ['name' => $layer['name']]) }}
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($relevant as $match)
                                                <button type="button"
                                                        wire:click="useSimilar({{ $i }}, '{{ $match['kind'] }}', '{{ $match['kind'] === 'custom' ? $match['id'] : $match['role'] }}')"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-amber-300 dark:border-amber-700 px-2.5 py-1 text-xs hover:bg-amber-100 dark:hover:bg-amber-900/40">
                                                    <span class="material-symbols-outlined text-[15px]">merge</span>
                                                    {{ $match['kind'] === 'custom'
                                                        ? __('imports.gdb.similar.add_to', ['name' => $match['name']])
                                                        : __('imports.gdb.similar.use_role', ['role' => __('imports.gdb.roles.'.$match['role'])]) }}
                                                    <span class="opacity-60">({{ (int) round($match['score'] * 100) }}%)</span>
                                                </button>
                                            @endforeach
                                            <span class="self-center text-xs">{{ __('imports.gdb.similar.or_keep') }}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- What happens to projects and buildings already on record. --}}
        @foreach (['projects', 'buildings'] as $table)
            @if ($roleUsed($table))
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                    <span class="text-on-surface dark:text-white">
                        {{ __('imports.gdb.mode_label', ['table' => __('imports.gdb.roles.'.$table), 'count' => number_format($gdb['existing'][$table] ?? 0)]) }}
                    </span>
                    <select wire:model.live="options.modes.{{ $table }}" class="{{ $select }}">
                        <option value="replace">{{ __('imports.gdb.modes.replace') }}</option>
                        <option value="append">{{ __('imports.gdb.modes.append') }}</option>
                    </select>
                </div>
            @endif
        @endforeach
        @if (collect($options['layers'] ?? [])->where('role', 'parcels')->count() > 1)
            <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">{{ __('imports.gdb.one_parcels_layer') }}</p>
        @endif

        {{-- Every field of every layer: how full, how varied, what it holds. --}}
        <div class="mt-4 space-y-2">
            @foreach ($gdb['layers'] as $layer)
                <details class="rounded-lg bg-surface-container dark:bg-white/5">
                    <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-on-surface dark:text-white">
                        {{ __('imports.gdb.field_profile', ['layer' => $layer['name']]) }}
                    </summary>
                    <div class="overflow-x-auto px-3 pb-3">
                        <table class="w-full text-xs">
                            <thead>
                                <tr>
                                    <th class="{{ $th }}">{{ __('imports.gdb.field') }}</th>
                                    <th class="{{ $th }}">{{ __('imports.gdb.type') }}</th>
                                    <th class="{{ $th }}">{{ __('imports.gdb.filled') }}</th>
                                    <th class="{{ $th }}">{{ __('imports.gdb.distinct') }}</th>
                                    <th class="{{ $th }}">{{ __('imports.gdb.samples') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                                @foreach ($layer['fields'] as $field)
                                    @php $pct = $layer['count'] > 0 ? (int) round($field['filled'] * 100 / $layer['count']) : 0; @endphp
                                    <tr class="{{ $field['filled'] === 0 ? 'opacity-50' : '' }}">
                                        <td class="px-3 py-1.5 font-medium text-on-surface dark:text-white" dir="ltr">{{ $field['name'] }}</td>
                                        <td class="px-3 py-1.5 text-on-surface-variant dark:text-on-primary-container" dir="ltr">{{ $field['type'] }}</td>
                                        <td class="px-3 py-1.5 whitespace-nowrap">
                                            <span class="inline-block h-1.5 w-16 rounded bg-outline-variant/40 align-middle">
                                                <span class="block h-1.5 rounded {{ $pct === 100 ? 'bg-secondary' : ($pct === 0 ? 'bg-error' : 'bg-amber-500') }}" style="width: {{ $pct }}%"></span>
                                            </span>
                                            <span class="data-tabular text-on-surface dark:text-white">{{ $field['filled'] }}/{{ $layer['count'] }}</span>
                                        </td>
                                        <td class="px-3 py-1.5 data-tabular text-on-surface dark:text-white">{{ $field['distinct'] }}</td>
                                        <td class="px-3 py-1.5 text-on-surface-variant dark:text-on-primary-container" dir="auto">{{ implode(' · ', $field['samples']) ?: __('imports.gdb.empty') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    {{-- 2. Attachments, photos and the rest of the file --}}
    <section class="{{ $card }}">
        <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">attach_file</span>{{ __('imports.gdb.attachments') }}</h3>
        <ul class="space-y-1 text-sm text-on-surface dark:text-white">
            <li>
                @if ($gdb['attachments'] === [])
                    {{ __('imports.gdb.no_attachments') }}
                @else
                    {{ __('imports.gdb.attachment_tables') }}:
                    @foreach ($gdb['attachments'] as $att)
                        <span dir="ltr">{{ $att['table'] }}</span> ({{ number_format($att['count']) }}){{ $loop->last ? '' : '،' }}
                    @endforeach
                    <span class="block {{ $muted }}">{{ __('imports.gdb.attachments_not_imported') }}</span>
                @endif
            </li>
            @php
                $photoFields = collect($gdb['layers'])->flatMap(fn ($l) => collect($l['fields'])
                    ->filter(fn ($f) => preg_match('/photo|image|pic|صور/iu', $f['name']))
                    ->map(fn ($f) => $l['name'].'.'.$f['name'].' — '.$f['filled'].'/'.$l['count']));
            @endphp
            @if ($photoFields->isNotEmpty())
                <li>{{ __('imports.gdb.photo_fields') }}: <span dir="ltr">{{ $photoFields->implode('، ') }}</span></li>
            @endif
            <li>{{ $gdb['relationships'] === [] ? __('imports.gdb.no_relationships') : __('imports.gdb.relationships').': '.implode('، ', $gdb['relationships']) }}</li>
            <li class="{{ $muted }}">{{ __('imports.gdb.system_tables', ['count' => count($gdb['system_tables'])]) }}</li>
        </ul>
    </section>

    @if ($roleUsed('parcels'))
        {{-- 3. Fixed-choice values --}}
        <section class="{{ $card }}">
            <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">rule</span>{{ __('imports.gdb.coded_values') }}</h3>
            <p class="mb-3 {{ $muted }}">{{ __('imports.gdb.coded_values_hint') }}</p>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($a['enums'] as $field => $values)
                    <div class="rounded-lg bg-surface-container dark:bg-white/5 p-3">
                        <p class="mb-2 text-sm font-semibold text-on-surface dark:text-white" dir="ltr">{{ $field }}</p>
                        <ul class="space-y-1 text-sm">
                            @foreach ($values as $v)
                                <li class="flex items-center justify-between gap-2">
                                    <span class="text-on-surface dark:text-white">{{ $v['value'] }} <span class="{{ $muted }}">×{{ $v['count'] }}</span></span>
                                    @if ($v['label'] !== null)
                                        <span class="text-xs text-secondary">✓ {{ $v['label'] }}</span>
                                    @else
                                        <span class="text-xs text-error">✗ {{ __('imports.gdb.unknown_value') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- 4. Districts --}}
        <section class="{{ $card }}">
            <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">holiday_village</span>{{ __('imports.gdb.districts') }}</h3>
            <p class="mb-3 {{ $muted }}">{{ __('imports.gdb.districts_hint') }}</p>
            {{-- How every parcel finds its district, unless a row or a parcel says otherwise. --}}
            <div class="mb-4 rounded-lg bg-surface-container dark:bg-white/5 p-3">
                <p class="mb-2 text-sm font-semibold text-on-surface dark:text-white">{{ __('imports.gdb.district_match') }}</p>
                <div class="space-y-1.5">
                    @foreach (['name', 'map'] as $choice)
                        <label class="{{ $radio }}">
                            <input type="radio" wire:model.live="options.district_match" value="{{ $choice }}" class="mt-1">
                            <span>{{ __('imports.gdb.district_matches.'.$choice) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mb-4 max-w-md">
                <x-form.search-select name="options.default_city_id" source="cities" live
                                      :value="$options['default_city_id'] ?? null"
                                      :label="__('imports.gdb.default_city')" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container dark:bg-white/5">
                        <tr>
                            <th class="{{ $th }}">{{ __('imports.gdb.district_in_file') }}</th>
                            <th class="{{ $th }}">{{ __('imports.gdb.features') }}</th>
                            <th class="{{ $th }}">{{ __('imports.gdb.match_method') }}</th>
                            <th class="{{ $th }}">{{ __('imports.gdb.row_match') }}</th>
                            <th class="{{ $th }}">{{ __('imports.gdb.match_district') }}</th>
                            <th class="{{ $th }}">{{ __('imports.gdb.or_create_in') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                        @foreach ($options['districts'] ?? [] as $i => $row)
                            <tr wire:key="district-{{ $i }}">
                                <td class="{{ $td }} font-medium">
                                    @if ($row['name'] === '')
                                        <span class="text-on-surface-variant">{{ __('imports.gdb.no_district_name') }}</span>
                                    @else
                                        {{ $row['name'] }}
                                    @endif
                                    @if (count($row['candidates'] ?? []) > 1)
                                        <span class="block text-xs text-amber-700 dark:text-amber-300">
                                            {{ __('imports.gdb.several_matches', ['cities' => collect($row['candidates'])->pluck('city')->implode('، ')]) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="{{ $td }} data-tabular">{{ $row['count'] ?? '' }}</td>
                                <td class="px-3 py-2 text-xs">
                                    @php
                                        $method = $row['method'] ?? 'none';
                                        $badge = [
                                            'name_map' => 'bg-secondary/15 text-secondary',
                                            'map' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            'name' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                            'none' => 'bg-surface-container text-on-surface-variant',
                                        ][$method] ?? '';
                                    @endphp
                                    <span class="inline-block whitespace-nowrap rounded-full px-2 py-0.5 font-semibold {{ $badge }}">
                                        {{ __('imports.gdb.methods.'.$method, ['share' => $row['map_share'] ?? 0, 'sampled' => $row['map_sampled'] ?? 0]) }}
                                    </span>
                                    @if (! empty($row['conflict']))
                                        <span class="mt-1 block text-amber-700 dark:text-amber-300">
                                            {{ __('imports.gdb.map_conflict', ['name' => $row['conflict']]) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:model.live="options.districts.{{ $i }}.match" class="{{ $select }}">
                                        <option value="">{{ __('imports.gdb.row_matches.default', ['method' => __('imports.gdb.row_matches.'.($options['district_match'] ?? 'name'))]) }}</option>
                                        <option value="name">{{ __('imports.gdb.row_matches.name') }}</option>
                                        <option value="map">{{ __('imports.gdb.row_matches.map') }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 min-w-[220px]">
                                    <x-form.search-select name="options.districts.{{ $i }}.district_id" source="districts" live
                                                          :value="$row['district_id'] ?? null" :label="''"
                                                          :placeholder="__('imports.gdb.new_district')" />
                                </td>
                                <td class="px-3 py-2 min-w-[200px]">
                                    @if (empty($row['district_id']))
                                        @if ($row['name'] === '')
                                            <label class="mb-1 block text-xs text-on-surface-variant dark:text-on-primary-container">
                                                {{ __('imports.gdb.new_district_name') }}
                                                <input type="text" wire:model.blur="options.districts.{{ $i }}.new_name" maxlength="150" dir="auto"
                                                       class="{{ $select }} mt-0.5 w-full">
                                            </label>
                                        @endif
                                        <x-form.search-select name="options.districts.{{ $i }}.city_id" source="cities" live
                                                              :value="$row['city_id'] ?? null" :label="''"
                                                              :placeholder="__('imports.gdb.default_city')" />
                                        @if (empty($row['city_id']) && empty($options['default_city_id']))
                                            <span class="block text-xs text-error">{{ __('imports.gdb.no_city') }}</span>
                                        @endif
                                    @else
                                        <span class="{{ $muted }}">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- One parcel, its own district — whatever its row or the method says. --}}
            <div class="mt-5">
                <p class="mb-1 text-sm font-semibold text-on-surface dark:text-white">{{ __('imports.gdb.parcel_exceptions') }}</p>
                <p class="mb-2 {{ $muted }}">{{ __('imports.gdb.parcel_exceptions_hint') }}</p>
                <datalist id="gdb-parcel-ids">
                    @foreach ($a['parcel_list'] ?? [] as $parcel)
                        <option value="{{ $parcel['geo_id'] }}">{{ __('imports.gdb.parcel_option', ['no' => $parcel['parcel_no'] ?: '—', 'district' => $parcel['district'] ?: '—']) }}</option>
                    @endforeach
                </datalist>
                <div class="space-y-2">
                    @foreach ($options['parcel_districts'] ?? [] as $e => $exception)
                        <div wire:key="parcel-exception-{{ $e }}" class="flex flex-wrap items-end gap-2">
                            <input type="text" list="gdb-parcel-ids" wire:model.blur="options.parcel_districts.{{ $e }}.geo_id" dir="ltr"
                                   placeholder="Geo_ID" class="{{ $select }} w-56">
                            <div class="min-w-[240px]">
                                <x-form.search-select name="options.parcel_districts.{{ $e }}.district_id" source="districts" live
                                                      :value="$exception['district_id'] ?? null" :label="''"
                                                      :placeholder="__('imports.gdb.match_district')" />
                            </div>
                            <button type="button" wire:click="removeParcelException({{ $e }})"
                                    class="p-1.5 rounded-lg text-on-surface-variant hover:bg-error/10 hover:text-error">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                            @php $known = collect($a['parcel_list'] ?? [])->firstWhere('geo_id', $exception['geo_id'] ?? null); @endphp
                            @if (($exception['geo_id'] ?? '') !== '' && $known === null)
                                <span class="text-xs text-error">{{ __('imports.gdb.parcel_not_in_file') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="addParcelException"
                        class="mt-2 inline-flex items-center gap-1 rounded-lg border border-outline-variant dark:border-white/10 px-3 py-1 text-xs text-on-surface dark:text-white hover:bg-surface-container">
                    <span class="material-symbols-outlined text-[16px]">add</span>{{ __('imports.gdb.add_parcel_exception') }}
                </button>
            </div>
        </section>

        {{-- 5. Plans --}}
        <section class="{{ $card }}">
            <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">map</span>{{ __('imports.gdb.plans') }}</h3>
            <p class="mb-2 text-sm text-on-surface dark:text-white">
                @foreach ($a['plans'] as $plan => $n)
                    <span class="me-3 whitespace-nowrap">{{ $plan }} <span class="{{ $muted }}">×{{ $n }}</span></span>
                @endforeach
                @if ($a['plans_empty'] > 0)
                    <span class="whitespace-nowrap">{{ __('imports.gdb.empty') }} <span class="{{ $muted }}">×{{ $a['plans_empty'] }}</span></span>
                @endif
            </p>
            <label class="block text-sm text-on-surface dark:text-white">
                {{ __('imports.gdb.plan_placeholders') }}
                <input type="text" wire:model.blur="options.plan_placeholders" class="{{ $select }} mt-1 w-full max-w-md">
            </label>
            <p class="mt-1 {{ $muted }}">{{ __('imports.gdb.plan_placeholders_hint') }}</p>
            <div class="mt-3 space-y-1.5">
                @foreach (['district_plan', 'none'] as $choice)
                    <label class="{{ $radio }}"><input type="radio" wire:model.live="options.no_plan" value="{{ $choice }}" class="mt-1">{{ __('imports.gdb.no_plan.'.$choice) }}</label>
                @endforeach
            </div>
        </section>

        {{-- 6. Deeds without a number --}}
        <section class="{{ $card }}">
            <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">description</span>{{ __('imports.gdb.deeds') }}</h3>
            <p class="mb-3 text-sm text-on-surface dark:text-white">
                {{ __('imports.gdb.deeds_counts', ['with' => $a['deeds_with_number'], 'without' => $a['deeds_without_number']]) }}
            </p>
            @if ($a['deeds_without_number'] > 0)
                <div class="space-y-2">
                    <label class="{{ $radio }}"><input type="radio" wire:model.live="options.deedless" value="placeholder" class="mt-1">{{ __('imports.gdb.deedless.placeholder') }}</label>
                    <label class="{{ $radio }}"><input type="radio" wire:model.live="options.deedless" value="skip" class="mt-1">{{ __('imports.gdb.deedless.skip') }}</label>
                </div>
            @endif
        </section>

        {{-- 7. Boundaries --}}
        <section class="{{ $card }}">
            <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">border_outer</span>{{ __('imports.gdb.borders') }}</h3>
            <p class="mb-3 text-sm text-on-surface dark:text-white">
                {{ __('imports.gdb.borders_counts', ['first' => $a['borders_first'], 'second' => $a['borders_second'], 'total' => $total]) }}
            </p>
            <div class="space-y-2">
                @foreach (['first', 'second', 'prefer_second'] as $choice)
                    <label class="{{ $radio }}"><input type="radio" wire:model.live="options.borders" value="{{ $choice }}" class="mt-1">{{ __('imports.gdb.border_sets.'.$choice) }}</label>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-on-surface dark:text-white">
                {{ __('imports.gdb.office') }}
                <select wire:model.live="options.office_id" class="{{ $select }}">
                    <option value="">{{ __('imports.gdb.no_office') }}</option>
                    @foreach ($offices as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @if (empty($options['office_id']))
                    <label class="flex items-center gap-2">
                        {{ __('imports.gdb.new_office') }}
                        <input type="text" wire:model.blur="options.office_name" maxlength="150" dir="auto" class="{{ $select }} w-64"
                               placeholder="{{ __('imports.gdb.new_office_placeholder') }}">
                    </label>
                @endif
            </div>
        </section>

        {{-- 8. Survey decision: Qrar and Folder --}}
        <section class="{{ $card }}">
            <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">gavel</span>{{ __('imports.gdb.decision') }}</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="mb-2 text-sm font-semibold text-on-surface dark:text-white" dir="ltr">Qrar</p>
                    <p class="mb-2 {{ $muted }}">{{ $a['qrar'] === [] ? __('imports.gdb.empty') : implode(' · ', array_keys($a['qrar'])) }}</p>
                    @foreach (['number', 'source', 'ignore'] as $choice)
                        <label class="{{ $radio }} mb-1"><input type="radio" wire:model.live="options.qrar" value="{{ $choice }}" class="mt-1">{{ __('imports.gdb.qrar.'.$choice) }}</label>
                    @endforeach
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold text-on-surface dark:text-white" dir="ltr">Folder</p>
                    <p class="mb-2 {{ $muted }}">{{ $a['folder'] === [] ? __('imports.gdb.empty') : implode(' · ', array_keys($a['folder'])) }}</p>
                    @foreach (['folder', 'ignore'] as $choice)
                        <label class="{{ $radio }} mb-1"><input type="radio" wire:model.live="options.folder" value="{{ $choice }}" class="mt-1">{{ __('imports.gdb.folder.'.$choice) }}</label>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 9. Owners and portfolios --}}
        <section class="{{ $card }}">
            <h3 class="{{ $h3 }}"><span class="material-symbols-outlined text-[20px]">group</span>{{ __('imports.gdb.owners') }}</h3>
            <p class="mb-2 text-sm text-on-surface dark:text-white">{{ __('imports.gdb.owners_count', ['count' => $a['owners']]) }}</p>
            @if ($a['owner_ids_odd'] !== [])
                <p class="mb-3 text-xs text-amber-700 dark:text-amber-300">
                    {{ __('imports.gdb.odd_ids') }} <span dir="ltr">{{ implode('، ', $a['owner_ids_odd']) }}</span>
                </p>
            @endif
            @if ($a['portfolios'] !== [])
                <label class="{{ $radio }}">
                    <input type="checkbox" wire:model.live="options.portfolios" class="mt-1">
                    {{ __('imports.gdb.portfolios', ['count' => count($a['portfolios'])]) }}
                </label>
                <p class="mt-1 ms-6 {{ $muted }}">
                    @foreach (array_slice($a['portfolios'], 0, 12, true) as $name => $n)
                        {{ $name }} (×{{ $n }}){{ $loop->last ? '' : '،' }}
                    @endforeach
                </p>
            @endif
        </section>
    @endif

    <p class="{{ $muted }}">{{ __('imports.gdb.empty_never_erases') }}</p>
</div>
