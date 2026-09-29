<div class="space-y-4">
    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl p-6
                border border-outline-variant dark:border-white/10 shadow-sm">
        <h1 class="text-lg font-bold text-on-surface dark:text-white">{{ __('map_layers.title') }}</h1>
        <p class="mt-1 text-sm text-on-surface-variant dark:text-on-primary-container">{{ __('map_layers.subtitle') }}</p>
    </div>

    <div class="bg-surface-container-lowest dark:bg-[#1a1f2e] rounded-2xl
                border border-outline-variant dark:border-white/10 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline-variant dark:border-white/10 bg-surface-container dark:bg-[#1e2435]">
                        @foreach (['name', 'geometry', 'features', 'fields', 'source', 'on_map'] as $col)
                            <th class="text-start px-4 py-3 font-semibold text-on-surface-variant dark:text-on-primary-container">{{ __('map_layers.col.'.$col) }}</th>
                        @endforeach
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant dark:divide-white/10">
                    @forelse ($layers as $layer)
                        <tr wire:key="map-layer-{{ $layer->id }}">
                            @if ($editing === $layer->id)
                                <td class="px-4 py-3" colspan="6">
                                    <div class="flex flex-wrap items-start gap-3">
                                        <div>
                                            <input type="text" wire:model.live.debounce.400ms="name" maxlength="150" dir="auto"
                                                   class="w-64 rounded-lg border border-outline-variant dark:border-white/10 bg-surface-container-lowest dark:bg-[#252b3b] px-2 py-1 text-sm text-on-surface dark:text-white">
                                            @error('name') <p class="mt-1 text-xs text-error">{{ $message }}</p> @enderror
                                            @if ($similar !== [])
                                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                                    {{ __('map_layers.similar', ['names' => implode('، ', $similar)]) }}
                                                </p>
                                            @endif
                                        </div>
                                        <label class="flex items-center gap-2 text-sm text-on-surface dark:text-white">
                                            {{ __('map_layers.color') }}
                                            <input type="color" wire:model.live="color" class="h-8 w-10 rounded border border-outline-variant">
                                        </label>
                                        <label class="flex items-center gap-2 text-sm text-on-surface dark:text-white">
                                            <input type="checkbox" wire:model.live="visible" class="accent-secondary">
                                            {{ __('map_layers.visible') }}
                                        </label>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-end whitespace-nowrap">
                                    <button wire:click="save" class="px-3 py-1 rounded-lg text-xs font-semibold bg-secondary text-white hover:brightness-110">{{ __('common.save') }}</button>
                                    <button wire:click="cancel" class="px-3 py-1 rounded-lg text-xs text-on-surface-variant hover:bg-surface-container">{{ __('common.cancel') }}</button>
                                </td>
                            @else
                                <td class="px-4 py-3 text-on-surface dark:text-white">
                                    <span class="inline-block w-3 h-3 rounded-sm me-1.5 align-middle" style="background: {{ $layer->color }}"></span>
                                    <span dir="auto">{{ $layer->name }}</span>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant dark:text-on-primary-container" dir="ltr">{{ $layer->geometry_type ?? '—' }}</td>
                                <td class="px-4 py-3 data-tabular text-on-surface dark:text-white">{{ number_format($layer->feature_count) }}</td>
                                <td class="px-4 py-3 text-xs text-on-surface-variant dark:text-on-primary-container" dir="ltr">
                                    {{ collect($layer->fields ?? [])->pluck('name')->take(6)->implode(', ') }}{{ count($layer->fields ?? []) > 6 ? '…' : '' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-on-surface-variant dark:text-on-primary-container">
                                    {{ $layer->source ?? '—' }}
                                    <span class="block">{{ $uploaders[$layer->created_by] ?? '' }} · {{ $layer->updated_at?->diffForHumans() }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <button wire:click="toggleVisible({{ $layer->id }})" title="{{ __('map_layers.toggle') }}"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-surface-container dark:hover:bg-white/5
                                                   {{ $layer->visible_by_default ? 'text-secondary' : 'text-on-surface-variant dark:text-on-primary-container' }}">
                                        <span class="material-symbols-outlined text-[16px]">{{ $layer->visible_by_default ? 'visibility' : 'visibility_off' }}</span>
                                        {{ $layer->visible_by_default ? __('map_layers.shown') : __('map_layers.hidden') }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-end whitespace-nowrap">
                                    <a href="{{ route('map-layers.download', $layer) }}" title="{{ __('map_layers.download') }}"
                                       class="inline-block p-1.5 rounded-lg text-on-surface-variant hover:bg-secondary/10 hover:text-secondary">
                                        <span class="material-symbols-outlined text-[18px] align-middle">download</span>
                                    </a>
                                    <button wire:click="edit({{ $layer->id }})" title="{{ __('common.edit') }}"
                                            class="p-1.5 rounded-lg text-on-surface-variant hover:bg-primary/10 hover:text-primary">
                                        <span class="material-symbols-outlined text-[18px]">edit</span>
                                    </button>
                                    <button wire:click="delete({{ $layer->id }})" wire:confirm="{{ __('map_layers.delete_confirm', ['name' => $layer->name, 'count' => $layer->feature_count]) }}"
                                            title="{{ __('common.delete') }}"
                                            class="p-1.5 rounded-lg text-on-surface-variant hover:bg-error/10 hover:text-error">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant dark:text-on-primary-container">
                                {{ __('map_layers.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
