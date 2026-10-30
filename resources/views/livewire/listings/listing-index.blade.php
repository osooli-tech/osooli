<div>
    @php
        $inputCls = 'w-full px-3 py-2 text-sm rounded-xl bg-surface-container dark:bg-[#252b3b] border border-outline-variant dark:border-white/10 text-on-surface dark:text-white focus:outline-none focus:ring-2 focus:ring-secondary/40';
        $chip = fn (\App\Enums\ListingStatus $s) => match ($s) {
            \App\Enums\ListingStatus::Pending => 'bg-tertiary-container/30 text-on-tertiary-container dark:text-tertiary-fixed-dim',
            \App\Enums\ListingStatus::Approved => 'bg-secondary/15 text-secondary',
            \App\Enums\ListingStatus::Rejected => 'bg-error/10 text-error',
            \App\Enums\ListingStatus::Withdrawn => 'bg-surface-container dark:bg-white/5 text-on-surface-variant dark:text-on-primary-container',
        };
    @endphp

    {{-- ── Header ── --}}
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
        <p class="text-sm text-on-surface-variant dark:text-on-primary-container">
            {{ $this->counts['all'] }} {{ __('listings.total') }}
        </p>
        <div class="relative w-full sm:w-72">
            <span class="absolute inset-y-0 end-3 flex items-center pointer-events-none text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[18px]">search</span>
            </span>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="{{ __('listings.search_placeholder') }}" class="{{ $inputCls }} pe-10" />
        </div>
    </div>

    {{-- ── Status tabs ── --}}
    <div class="flex items-center gap-1 flex-wrap mb-5 bg-surface-container dark:bg-[#252b3b] rounded-xl p-1 w-fit">
        @foreach (['all' => __('listings.all')] + collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all() as $key => $label)
            <button wire:click="$set('statusFilter', '{{ $key }}')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors
                           {{ $statusFilter === $key ? 'bg-secondary text-white shadow-sm' : 'text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container-high dark:hover:bg-white/10' }}">
                {{ $label }}
                <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] rounded-full text-[10px] font-bold px-1
                             {{ $statusFilter === $key ? 'bg-white/20 text-white' : 'bg-outline-variant/40 dark:bg-white/10' }}">
                    {{ $this->counts[$key] ?? 0 }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- ── Map: every listing, coloured by status; a click opens it here ── --}}
    @if ($this->map['features'] !== [] && config('services.mapbox.token'))
        <div wire:ignore class="mb-5 bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden"
             x-data="listingsReviewMap(@js($this->map), @js(['parcel' => __('listings.col_parcel'), 'inquiries' => __('listings.col_inquiries')]))">
            <div x-ref="map" class="h-[380px] w-full"></div>
            <div class="flex flex-wrap items-center gap-4 px-4 py-2.5 text-xs text-on-surface-variant dark:text-on-primary-container border-t border-outline-variant dark:border-white/10">
                @foreach (\App\Enums\ListingStatus::cases() as $s)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded-sm {{ ['pending' => 'bg-tertiary-container', 'approved' => 'bg-secondary', 'rejected' => 'bg-error', 'withdrawn' => 'bg-outline'][$s->value] }}"></span>{{ $s->label() }}
                    </span>
                @endforeach
                <span class="inline-flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm border-2 border-error-container bg-transparent"></span>{{ __('listings.map_new_interest') }}
                </span>
            </div>
        </div>
    @endif

    {{-- ── Table ── --}}
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
        @if ($listings->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 gap-3 text-on-surface-variant dark:text-on-primary-container">
                <span class="material-symbols-outlined text-[48px] opacity-30">storefront</span>
                <p class="text-sm">{{ __('listings.empty_staff') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline-variant dark:border-white/10 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant dark:text-on-primary-container">
                            <th class="px-5 py-3 text-start">{{ __('listings.col_parcel') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('listings.col_seller') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('listings.col_phone') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('listings.col_status') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('listings.col_inquiries') }}</th>
                            <th class="px-5 py-3 text-start hidden md:table-cell">{{ __('listings.col_date') }}</th>
                            <th class="px-5 py-3 text-start">{{ __('listings.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40 dark:divide-white/5">
                        @foreach ($listings as $item)
                            <tr class="hover:bg-surface-container dark:hover:bg-white/5 transition-colors" wire:key="listing-{{ $item->id }}">
                                <td class="px-5 py-3.5 font-semibold ltr">{{ $item->parcel?->parcel_no ?? '—' }}</td>
                                <td class="px-5 py-3.5">{{ $item->owner?->name ?? '—' }}</td>
                                <td class="px-5 py-3.5 ltr" dir="ltr">{{ $item->contact_phone }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-lg {{ $chip($item->status) }}">{{ $item->status->label() }}</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if ($item->new_inquiries_count > 0)
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-lg bg-tertiary-container/40 text-on-tertiary-container dark:text-tertiary-fixed-dim">
                                            <span class="material-symbols-outlined text-[14px]">front_hand</span>{{ $item->new_inquiries_count }}
                                        </span>
                                    @else
                                        <span class="text-on-surface-variant dark:text-on-primary-container">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 hidden md:table-cell data-tabular text-on-surface-variant dark:text-on-primary-container">{{ $item->created_at?->format('Y-m-d') }}</td>
                                <td class="px-5 py-3.5">
                                    <button wire:click="openListing({{ $item->id }})" class="text-xs font-medium text-secondary hover:underline">{{ __('listings.open') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-outline-variant dark:border-white/10">{{ $listings->links() }}</div>
        @endif
    </div>

    {{-- ── Review modal ── --}}
    @if ($showModal && ($listing = $this->viewing))
        @php
            $holders = $listing->parcel?->heldDeed?->owners ?? collect();
            $sellerHolds = $holders->contains('id', $listing->owner_id);
        @endphp
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/50" wire:click.self="closeModal">
            <div class="w-full max-w-2xl max-h-[90vh] flex flex-col rounded-2xl bg-surface-container-lowest dark:bg-[#1a1f2e] shadow-xl">
                <div class="px-6 py-4 border-b border-outline-variant dark:border-white/10 flex items-center justify-between gap-3 shrink-0">
                    <h3 class="font-semibold">{{ __('listings.col_parcel') }} <span class="ltr">{{ $listing->parcel?->parcel_no }}</span></h3>
                    <span class="text-xs font-medium px-2 py-0.5 rounded-lg {{ $chip($listing->status) }}">{{ $listing->status->label() }}</span>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-5">
                    {{-- The whole parcel, as only staff see it --}}
                    @php
                        $parcel = $listing->parcel;
                        $deed = $parcel?->heldDeed;
                        $frontage = \App\Support\ParcelFrontage::read($parcel?->boundary);
                        $streets = collect($frontage['streets'] ?? [])->map(fn ($w, $side) => __('portal.side.'.$side).($w ? ' '.rtrim(rtrim(number_format($w, 1), '0'), '.').' '.__('portal.metre_short') : ''))->implode('، ');
                        $place = collect([$parcel?->plan?->district?->name_ar, $parcel?->plan?->district?->city?->name_ar])->filter()->implode(' · ');
                    @endphp
                    <section class="flex flex-col sm:flex-row gap-4 rounded-xl p-4 bg-surface-container-low dark:bg-white/5">
                        <x-parcel-shape :geojson="$parcel?->getAttribute('geom_json')" class="w-24 h-24 self-center [&_path]:fill-tertiary-container/30 [&_path]:stroke-tertiary" />
                        <dl class="flex-1 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.parcel_no') }}</dt><dd class="font-semibold ltr">{{ $parcel?->parcel_no ?? '—' }}</dd></div>
                            <div><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.plan_no') }}</dt><dd class="font-medium ltr">{{ $parcel?->plan?->plan_no ?? '—' }}</dd></div>
                            <div><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.deed_no') }}</dt><dd class="font-medium ltr">{{ $deed?->deed_no ?? '—' }}</dd></div>
                            <div><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.deed_area') }}</dt><dd class="font-medium data-tabular">{{ $deed?->deed_area !== null ? number_format((float) $deed->deed_area, 2).' '.__('dashboard.area_unit_sqm') : '—' }}</dd></div>
                            <div><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.price') }}</dt><dd class="font-medium data-tabular">{{ $parcel?->parcel_price ? number_format((float) $parcel->parcel_price).' '.__('parcels.sar') : '—' }}</dd></div>
                            <div><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.asset_type') }}</dt><dd class="font-medium">{{ $parcel?->asset_type ?? '—' }}</dd></div>
                            <div class="col-span-2"><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.streets') }}</dt><dd class="font-medium">{{ $streets ?: __('listings.no_streets') }}</dd></div>
                            <div class="col-span-2"><dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.place') }}</dt><dd class="font-medium">{{ $place ?: '—' }}</dd></div>
                        </dl>
                    </section>
                    @if ($parcel)
                        <a href="{{ route('parcels.show', $parcel->id) }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-medium text-secondary hover:underline">
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>{{ __('listings.open_parcel') }}
                        </a>
                    @endif

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.col_seller') }}</dt>
                            <dd class="font-medium">{{ $listing->owner?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.col_phone') }}</dt>
                            <dd class="font-medium ltr" dir="ltr">{{ $listing->contact_phone }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] text-on-surface-variant dark:text-on-primary-container">{{ __('listings.holders') }}</dt>
                            <dd class="flex flex-wrap gap-1.5 mt-1">
                                @forelse ($holders as $holder)
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $holder->id === $listing->owner_id ? 'bg-secondary/15 text-secondary' : 'bg-surface-container dark:bg-white/5' }}">{{ $holder->name }}</span>
                                @empty
                                    <span>—</span>
                                @endforelse
                            </dd>
                        </div>
                    </dl>

                    @if ($holders->count() > 1)
                        <p class="flex items-start gap-1.5 text-xs rounded-xl p-3 bg-tertiary-container/20 text-on-surface dark:text-white">
                            <span class="material-symbols-outlined text-[16px] text-tertiary">group</span>{{ __('listings.co_owned', ['count' => $holders->count()]) }}
                        </p>
                    @endif
                    @if (! $sellerHolds)
                        <p class="flex items-start gap-1.5 text-xs rounded-xl p-3 bg-error/10 text-error">
                            <span class="material-symbols-outlined text-[16px]">warning</span>{{ __('listings.seller_not_holder') }}
                        </p>
                    @endif
                    @if ($listing->status === \App\Enums\ListingStatus::Rejected && $listing->rejection_reason)
                        <p class="text-xs text-error">{{ __('listings.rejection_reason', ['reason' => $listing->rejection_reason]) }}</p>
                    @endif

                    {{-- Buyers who asked Sukooki to put them in touch --}}
                    <section class="border-t border-outline-variant dark:border-white/10 pt-5">
                        <h4 class="text-sm font-semibold mb-3">{{ __('listings.inquiries_title') }}</h4>
                        @forelse ($listing->inquiries as $inquiry)
                            <div class="flex flex-wrap items-start justify-between gap-3 py-2.5 border-b last:border-0 border-outline-variant/50 dark:border-white/5" wire:key="inquiry-{{ $inquiry->id }}">
                                <div class="min-w-0 text-sm">
                                    <p class="font-medium">{{ $inquiry->owner?->name ?? '—' }}
                                        <span class="ms-1 text-xs text-on-surface-variant dark:text-on-primary-container ltr" dir="ltr">{{ $inquiry->owner?->phone }}</span>
                                    </p>
                                    @if ($inquiry->note)
                                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mt-0.5">{{ $inquiry->note }}</p>
                                    @endif
                                    <p class="text-[11px] text-on-surface-variant dark:text-on-primary-container mt-0.5 data-tabular">{{ $inquiry->created_at?->format('Y-m-d H:i') }} · {{ $inquiry->status->label() }}</p>
                                </div>
                                @can('listings.manage')
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if ($inquiry->status === \App\Enums\InquiryStatus::New)
                                            <button wire:click="markInquiry({{ $inquiry->id }}, 'contacted')" class="text-xs font-medium text-secondary hover:underline">{{ __('listings.mark_contacted') }}</button>
                                        @endif
                                        @if ($inquiry->status !== \App\Enums\InquiryStatus::Closed)
                                            <button wire:click="markInquiry({{ $inquiry->id }}, 'closed')" class="text-xs font-medium text-on-surface-variant dark:text-on-primary-container hover:underline">{{ __('listings.mark_closed') }}</button>
                                        @endif
                                    </div>
                                @endcan
                            </div>
                        @empty
                            <p class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('listings.inquiries_none') }}</p>
                        @endforelse
                    </section>

                    {{-- Owners this listing is hidden from --}}
                    <section class="border-t border-outline-variant dark:border-white/10 pt-5">
                        <h4 class="text-sm font-semibold">{{ __('listings.blocked_title') }}</h4>
                        <p class="text-xs text-on-surface-variant dark:text-on-primary-container mb-3">{{ __('listings.blocked_hint') }}</p>

                        <div class="flex flex-wrap gap-2 mb-3">
                            @forelse ($listing->blockedOwners as $blocked)
                                <span class="inline-flex items-center gap-1 text-xs ps-2.5 pe-1 py-1 rounded-full bg-error/10 text-error" wire:key="blocked-{{ $blocked->id }}">
                                    {{ $blocked->name }}
                                    @can('listings.manage')
                                        <button wire:click="unblock({{ $blocked->id }})" title="{{ __('listings.unblock') }}" class="w-5 h-5 rounded-full hover:bg-error/20 flex items-center justify-center">
                                            <span class="material-symbols-outlined text-[14px]">close</span>
                                        </button>
                                    @endcan
                                </span>
                            @empty
                                <span class="text-xs text-on-surface-variant dark:text-on-primary-container">{{ __('listings.no_blocked') }}</span>
                            @endforelse
                        </div>

                        @can('listings.manage')
                            <input wire:model.live.debounce.300ms="ownerSearch" type="search" placeholder="{{ __('listings.block_search') }}" class="{{ $inputCls }}" />
                            @if ($this->ownerMatches->isNotEmpty())
                                <ul class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-outline-variant dark:border-white/10 divide-y divide-outline-variant/40 dark:divide-white/5">
                                    @foreach ($this->ownerMatches as $match)
                                        <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm" wire:key="match-{{ $match->id }}">
                                            <span class="min-w-0 truncate">{{ $match->name }} <span class="text-xs text-on-surface-variant dark:text-on-primary-container ltr" dir="ltr">{{ $match->phone }}</span></span>
                                            <button wire:click="block({{ $match->id }})" class="shrink-0 text-xs font-medium text-error hover:underline">{{ __('listings.block_add') }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endcan
                    </section>

                    @can('listings.manage')
                        @if ($listing->status === \App\Enums\ListingStatus::Pending)
                            <div class="border-t border-outline-variant dark:border-white/10 pt-5">
                                <label class="block text-xs font-medium mb-1 text-on-surface-variant dark:text-on-primary-container">{{ __('listings.reject_reason') }}</label>
                                <textarea wire:model="rejectionReason" rows="2" class="{{ $inputCls }} resize-none"></textarea>
                                @error('rejectionReason') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    @endcan
                </div>

                <div class="px-6 py-4 border-t border-outline-variant dark:border-white/10 flex flex-wrap items-center justify-between gap-3 shrink-0">
                    <button wire:click="closeModal" class="px-4 py-2 text-sm rounded-xl border border-outline-variant dark:border-white/10 text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5 transition-colors">
                        {{ __('listings.close') }}
                    </button>
                    @can('listings.manage')
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($listing->status === \App\Enums\ListingStatus::Pending)
                                <button wire:click="reject" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl bg-error text-white hover:brightness-110">
                                    <span class="material-symbols-outlined text-[16px]">cancel</span>{{ __('listings.reject') }}
                                </button>
                                <button wire:click="approve" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl bg-secondary text-white hover:brightness-110">
                                    <span class="material-symbols-outlined text-[16px]">check_circle</span>{{ __('listings.approve') }}
                                </button>
                            @elseif ($listing->status === \App\Enums\ListingStatus::Approved)
                                <button wire:click="takeDown" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm rounded-xl bg-error text-white hover:brightness-110">
                                    <span class="material-symbols-outlined text-[16px]">block</span>{{ __('listings.take_down') }}
                                </button>
                            @endif
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    @endif
</div>
