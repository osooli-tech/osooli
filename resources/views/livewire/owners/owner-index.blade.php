<div class="space-y-4">

    {{-- Search bar --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-4
                border border-outline-variant dark:border-white/10 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">

            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
                    {{ __('owners.search_placeholder') }}
                </label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                                 text-on-surface-variant dark:text-on-primary-container pointer-events-none">
                        search
                    </span>
                    <input wire:model.live.debounce.400ms="search"
                           type="text"
                           placeholder="{{ __('owners.search_placeholder') }}"
                           class="w-full ps-9 pe-4 py-2 text-sm rounded-xl
                                  bg-surface-container dark:bg-[#252b3b]
                                  border border-outline-variant dark:border-white/10
                                  text-on-surface dark:text-white
                                  placeholder:text-on-surface-variant focus:outline-none
                                  focus:ring-2 focus:ring-primary/40" />
                </div>
            </div>

            @if ($search !== '')
                <button wire:click="$set('search', '')"
                        class="flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl
                               text-error border border-error/30 hover:bg-error/10 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('owners.clear') }}
                </button>
            @endif

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
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('owners.name') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('owners.national_id') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('owners.phone') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('owners.parcel_count') }}
                        </th>
                        <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">
                            {{ __('owners.deed_count') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($owners as $owner)
                        <tr class="border-b border-outline-variant dark:border-white/5 hover:bg-surface-container dark:hover:bg-white/5 transition-colors">

                            {{-- Name + avatar --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-secondary/15 dark:bg-secondary/25
                                                flex items-center justify-center shrink-0
                                                text-secondary font-bold text-sm">
                                        {{ mb_substr($owner->name, 0, 1) }}
                                    </div>
                                    <span class="font-medium text-on-surface dark:text-white">
                                        {{ $owner->name }}
                                    </span>
                                </div>
                            </td>

                            {{-- National ID --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular" dir="ltr">
                                {{ $owner->national_id ?? '—' }}
                            </td>

                            {{-- Phone --}}
                            <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container data-tabular" dir="ltr">
                                {{ $owner->phone ?? '—' }}
                            </td>

                            {{-- Parcel count --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium
                                             bg-primary/10 text-primary dark:bg-primary/20 dark:text-white/90">
                                    {{ $owner->parcel_count }}
                                </span>
                            </td>

                            {{-- Deed count --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium
                                             bg-secondary/10 text-secondary dark:bg-secondary/20 dark:text-white/90">
                                    {{ $owner->deeds_count }}
                                </span>
                            </td>

                            {{-- Expand button --}}
                            <td class="px-4 py-3">
                                <button wire:click="toggleExpand({{ $owner->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-xl
                                               border border-outline-variant dark:border-white/10
                                               text-on-surface-variant dark:text-on-primary-container
                                               hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                                    <span class="material-symbols-outlined text-[15px]">
                                        {{ isset($expanded[$owner->id]) && $expanded[$owner->id] ? 'keyboard_arrow_up' : 'keyboard_arrow_down' }}
                                    </span>
                                    {{ __('owners.view_parcels') }}
                                </button>
                            </td>

                        </tr>

                        {{-- Expanded parcels --}}
                        @if (isset($expanded[$owner->id]) && $expanded[$owner->id])
                            @php
                                $ownerParcels = $owner->deeds()
                                    ->with('parcel.plan')
                                    ->get()
                                    ->map(fn ($deed) => [
                                        'parcel_no'       => $deed->parcel?->parcel_no,
                                        'plan_no'         => $deed->parcel?->plan?->plan_no,
                                        'deed_no'         => $deed->deed_no,
                                        'deed_date_hijri' => $deed->deed_date_hijri,
                                        'deed_area'       => $deed->deed_area,
                                        'parcel_id'       => $deed->parcel?->id,
                                    ]);
                            @endphp
                            <tr>
                                <td colspan="6" class="bg-surface-container dark:bg-[#161f2e] px-4 py-4">
                                    <p class="text-xs font-semibold text-on-surface-variant dark:text-on-primary-container mb-3 uppercase tracking-wide">
                                        {{ __('owners.parcels_of') }} {{ $owner->name }}
                                    </p>
                                    <div class="overflow-x-auto rounded-xl border border-outline-variant dark:border-white/10">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="bg-surface dark:bg-[#1a2435] border-b border-outline-variant dark:border-white/10">
                                                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.parcel_no') }}</th>
                                                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.plan_no') }}</th>
                                                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.deed_no') }}</th>
                                                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.deed_date') }}</th>
                                                    <th class="text-start px-3 py-2 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('parcels.area_deed') }}</th>
                                                    <th class="px-3 py-2"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-outline-variant dark:divide-white/5">
                                                @foreach ($ownerParcels as $op)
                                                    <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                                                        <td class="px-3 py-2 font-medium text-on-surface dark:text-white data-tabular">{{ $op['parcel_no'] ?? '—' }}</td>
                                                        <td class="px-3 py-2 text-on-surface-variant dark:text-on-primary-container data-tabular">{{ $op['plan_no'] ?? '—' }}</td>
                                                        <td class="px-3 py-2 text-on-surface-variant dark:text-on-primary-container data-tabular">{{ $op['deed_no'] ?? '—' }}</td>
                                                        <td class="px-3 py-2 text-on-surface-variant dark:text-on-primary-container data-tabular">{{ $op['deed_date_hijri'] ?? '—' }}</td>
                                                        <td class="px-3 py-2 text-on-surface-variant dark:text-on-primary-container data-tabular">
                                                            {{ $op['deed_area'] ? number_format((float) $op['deed_area'], 0).' '.__('dashboard.area_unit_sqm') : '—' }}
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            @if ($op['parcel_id'])
                                                                <a href="{{ route('parcels.show', $op['parcel_id']) }}"
                                                                   class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline underline-offset-2">
                                                                    <span class="material-symbols-outlined text-[14px]">arrow_back_ios</span>
                                                                    {{ __('parcels.show') }}
                                                                </a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        @endif

                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <div class="flex flex-col items-center gap-3
                                            text-on-surface-variant dark:text-on-primary-container">
                                    <span class="material-symbols-outlined text-[48px] opacity-30">person_search</span>
                                    <p class="text-sm">{{ __('owners.no_results') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($owners->hasPages())
            <div class="px-4 py-3 border-t border-outline-variant dark:border-white/10">
                {{ $owners->links() }}
            </div>
        @endif

    </div>

</div>
