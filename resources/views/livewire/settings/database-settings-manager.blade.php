<div @if ($running) wire:poll.2s="poll" @endif>

    @php
        $card = 'bg-white dark:bg-[#0f2235] rounded-2xl shadow-sm border border-surface-container dark:border-[#1a2e42] p-6';
        $label = 'block text-xs font-semibold text-on-surface-variant dark:text-white/60 tracking-wide mb-1.5';
        $input = 'w-full px-4 py-2.5 rounded-xl border border-surface-container-highest dark:border-[#2a3f55] bg-surface dark:bg-[#1a2e42] text-on-surface dark:text-white placeholder-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary text-sm transition-all';
        $btnPrimary = 'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-secondary text-white hover:brightness-110 transition-all disabled:opacity-50';
        $btnGhost = 'inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border border-surface-container-highest dark:border-[#2a3f55] text-on-surface dark:text-white hover:bg-surface-container dark:hover:bg-white/5 transition-all disabled:opacity-50';
    @endphp

    {{-- ── Header ── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-on-surface dark:text-white">{{ __('database_settings.title') }}</h1>
            <p class="text-sm text-on-surface-variant dark:text-white/60 mt-1">{{ __('database_settings.subtitle') }}</p>
        </div>
        <button wire:click="refreshStatuses" wire:loading.attr="disabled" class="{{ $btnGhost }} shrink-0">
            <span class="material-symbols-outlined text-[18px]" wire:loading.class="animate-spin" wire:target="refreshStatuses">refresh</span>
            {{ __('database_settings.refresh') }}
        </button>
    </div>

    @if ($unreadable)
        <div class="mb-5 flex items-start gap-3 rounded-2xl bg-error-container text-on-error-container p-4 text-sm">
            <span class="material-symbols-outlined">warning</span>
            <p>{{ __('database_settings.unreadable_warning') }}</p>
        </div>
    @elseif (! is_file(\App\Support\Database\DatabaseSettings::path()))
        <div class="mb-5 flex items-start gap-3 rounded-2xl bg-secondary-container/60 text-on-secondary-container p-4 text-sm">
            <span class="material-symbols-outlined">info</span>
            <p>{{ __('database_settings.env_note') }}</p>
        </div>
    @endif

    {{-- ── The two databases ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start mb-6">
        @foreach (\App\Support\Database\DatabaseSettings::CONNECTIONS as $name)
            @php
                $status = $statuses[$name] ?? [];
                $isPrimary = $name === $primary;
            @endphp

            <div class="{{ $card }} {{ $isPrimary ? 'ring-2 ring-secondary' : '' }}" wire:key="db-{{ $name }}">

                {{-- Title + badges --}}
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-secondary/10 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-secondary">database</span>
                        </span>
                        <div>
                            <h2 class="font-bold text-on-surface dark:text-white">{{ __('database_settings.names.'.$name) }}</h2>
                            <span class="text-xs font-semibold {{ $isPrimary ? 'text-secondary' : 'text-on-surface-variant dark:text-white/60' }}">
                                {{ $isPrimary ? __('database_settings.primary') : __('database_settings.secondary') }}
                            </span>
                        </div>
                    </div>

                    @if (! empty($status['unconfigured']))
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-surface-container text-on-surface-variant dark:bg-white/10 dark:text-white/70">
                            {{ __('database_settings.unconfigured') }}
                        </span>
                    @elseif ($status['ok'] ?? false)
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                            {{ __('database_settings.connected') }}
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-error-container text-on-error-container">
                            {{ __('database_settings.unreachable') }}
                        </span>
                    @endif
                </div>

                {{-- Health --}}
                @if ($status['ok'] ?? false)
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm mb-3">
                        <dt class="text-on-surface-variant dark:text-white/60">{{ __('database_settings.version') }}</dt>
                        <dd class="text-on-surface dark:text-white" dir="ltr">{{ $status['version'] }}</dd>
                        <dt class="text-on-surface-variant dark:text-white/60">{{ __('database_settings.schema_ok') }}</dt>
                        <dd>
                            @if ($status['migrated'])
                                <span class="text-emerald-700 dark:text-emerald-300 font-semibold">✓</span>
                            @elseif (($status['counts']['parcels'] ?? null) === null)
                                <span class="text-on-surface-variant dark:text-white/60">{{ __('database_settings.schema_missing') }}</span>
                            @else
                                <span class="text-amber-700 dark:text-amber-300">{{ __('database_settings.schema_pending', ['count' => $status['pending']]) }}</span>
                            @endif
                        </dd>
                    </dl>

                    <div class="grid grid-cols-3 gap-2 mb-4">
                        @foreach (\App\Support\Database\ConnectionProbe::KEY_TABLES as $table)
                            <div class="rounded-xl bg-surface dark:bg-[#1a2e42] px-3 py-2">
                                <div class="text-[11px] text-on-surface-variant dark:text-white/60">{{ __('database_settings.tables.'.$table) }}</div>
                                <div class="font-bold text-on-surface dark:text-white">{{ isset($status['counts'][$table]) ? number_format($status['counts'][$table]) : '—' }}</div>
                            </div>
                        @endforeach
                    </div>
                @elseif (! empty($status['error']))
                    <p class="text-xs text-error mb-4 break-words" dir="ltr">{{ $status['error'] }}</p>
                @endif

                {{-- Switching --}}
                @if (! $isPrimary && $configured[$name])
                    <div class="flex flex-wrap gap-2 mb-5 pb-5 border-b border-surface-container dark:border-[#1a2e42]">
                        <button wire:click="startSync(true)"
                                wire:confirm="{{ __('database_settings.sync_then_switch_confirm') }}"
                                @disabled($running) class="{{ $btnPrimary }}">
                            <span class="material-symbols-outlined text-[18px]">sync_alt</span>
                            {{ __('database_settings.sync_then_switch') }}
                        </button>
                        <button wire:click="switchPrimary('{{ $name }}')"
                                wire:confirm="{{ __('database_settings.make_primary_confirm') }}"
                                @disabled($running) class="{{ $btnGhost }}">
                            <span class="material-symbols-outlined text-[18px]">swap_horiz</span>
                            {{ __('database_settings.make_primary') }}
                        </button>
                    </div>
                @endif

                {{-- Connection form --}}
                <form wire:submit="saveConnection('{{ $name }}')" class="space-y-3">
                    <p class="text-xs text-on-surface-variant dark:text-white/60">{{ __('database_settings.connections_hint') }}</p>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="{{ $label }}">{{ __('database_settings.host') }}</label>
                            <input type="text" wire:model="forms.{{ $name }}.host" dir="ltr" autocomplete="off" class="{{ $input }}">
                            @error("forms.{$name}.host") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('database_settings.port') }}</label>
                            <input type="text" inputmode="numeric" wire:model="forms.{{ $name }}.port" dir="ltr" class="{{ $input }}">
                            @error("forms.{$name}.port") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $label }}">{{ __('database_settings.database') }}</label>
                            <input type="text" wire:model="forms.{{ $name }}.database" dir="ltr" autocomplete="off" class="{{ $input }}">
                            @error("forms.{$name}.database") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="{{ $label }}">{{ __('database_settings.username') }}</label>
                            <input type="text" wire:model="forms.{{ $name }}.username" dir="ltr" autocomplete="off" class="{{ $input }}">
                            @error("forms.{$name}.username") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $label }}">{{ __('database_settings.password') }}</label>
                            <input type="password" wire:model="forms.{{ $name }}.password" dir="ltr" autocomplete="new-password"
                                   placeholder="{{ $storedPassword[$name] ? __('database_settings.password_keep') : __('database_settings.password_new') }}"
                                   class="{{ $input }}">
                            @error("forms.{$name}.password") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        @if ($name === 'pgsql')
                            <div>
                                <label class="{{ $label }}">{{ __('database_settings.sslmode') }}</label>
                                <select wire:model="forms.{{ $name }}.sslmode" dir="ltr" class="{{ $input }}">
                                    @foreach (['require', 'prefer', 'disable', 'verify-ca', 'verify-full'] as $mode)
                                        <option value="{{ $mode }}">{{ $mode }}</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-on-surface-variant dark:text-white/50 mt-1">{{ __('database_settings.sslmode_hint') }}</p>
                                @error("forms.{$name}.sslmode") <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    @if (isset($tests[$name]))
                        <div class="rounded-xl px-3 py-2 text-xs {{ $tests[$name]['ok'] ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-error-container text-on-error-container' }}">
                            @if ($tests[$name]['ok'])
                                {{ __('database_settings.test_ok', ['version' => $tests[$name]['version']]) }}
                            @else
                                <strong>{{ __('database_settings.test_failed') }}:</strong>
                                <span dir="ltr" class="break-words">{{ $tests[$name]['error'] }}</span>
                            @endif
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" wire:click="test('{{ $name }}')" wire:loading.attr="disabled" class="{{ $btnGhost }}">
                            <span class="material-symbols-outlined text-[18px]" wire:loading.class="animate-spin" wire:target="test('{{ $name }}')">network_check</span>
                            {{ __('database_settings.test') }}
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="{{ $btnPrimary }}">
                            <span class="material-symbols-outlined text-[18px]">save</span>
                            {{ __('database_settings.save') }}
                        </button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>

    {{-- ── Sync ── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start mb-6">

        <div class="{{ $card }}">
            <h2 class="text-base font-semibold text-on-surface dark:text-white mb-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">sync</span>
                {{ __('database_settings.sync_title') }}
            </h2>
            <p class="text-sm text-on-surface-variant dark:text-white/60 mb-4">{{ __('database_settings.sync_hint') }}</p>

            <div class="flex items-center gap-2 text-sm font-semibold text-on-surface dark:text-white mb-4" dir="ltr">
                <span>{{ __('database_settings.names.'.$primary) }}</span>
                <span class="material-symbols-outlined text-secondary">arrow_forward</span>
                <span>{{ __('database_settings.names.'.$secondary) }}</span>
            </div>

            @if ($run)
                @php
                    $state = $run['state'] ?? 'failed';
                    $percent = ($run['total'] ?? 0) > 0 ? (int) round(100 * ($run['done'] ?? 0) / $run['total']) : 0;
                @endphp
                <div class="rounded-xl bg-surface dark:bg-[#1a2e42] p-4 mb-4 text-sm">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="font-semibold text-on-surface dark:text-white">
                            {{ __('database_settings.steps.'.($run['step'] ?? 'finished')) }}
                        </span>
                        <span class="text-xs font-semibold
                            {{ $state === 'succeeded' ? 'text-emerald-700 dark:text-emerald-300' : ($state === 'running' ? 'text-secondary' : 'text-error') }}">
                            {{ __('database_settings.states.'.$state) }}
                        </span>
                    </div>

                    @if ($state === 'running')
                        <div class="h-2 rounded-full bg-surface-container dark:bg-white/10 overflow-hidden mb-2">
                            <div class="h-full bg-secondary transition-all" style="width: {{ $percent }}%"></div>
                        </div>
                        @if (! empty($run['table']))
                            <p class="text-xs text-on-surface-variant dark:text-white/60">
                                {{ __('database_settings.copying_table', ['table' => $run['table'], 'done' => $run['done'] + 1, 'total' => $run['total']]) }}
                            </p>
                        @endif
                    @elseif ($state === 'failed')
                        <p class="text-xs text-error break-words" dir="ltr">
                            {{ ($run['error'] ?? '') === 'stalled' ? __('database_settings.stalled') : $run['error'] }}
                        </p>
                    @endif
                </div>
            @endif

            <label class="flex items-start gap-2 text-sm text-on-surface dark:text-white mb-1 cursor-pointer">
                <input type="checkbox" wire:model="fresh" class="mt-0.5 rounded text-secondary focus:ring-secondary">
                <span>{{ __('database_settings.fresh_label') }}</span>
            </label>
            <p class="text-xs text-on-surface-variant dark:text-white/60 mb-4 ms-6">{{ __('database_settings.fresh_hint') }}</p>

            <button wire:click="startSync(false)"
                    wire:confirm="{{ __('database_settings.sync_now_confirm', ['from' => __('database_settings.names.'.$primary), 'to' => __('database_settings.names.'.$secondary)]) }}"
                    @disabled($running || ! $configured[$secondary])
                    class="{{ $btnPrimary }}">
                <span class="material-symbols-outlined text-[18px] {{ $running ? 'animate-spin' : '' }}">sync</span>
                {{ __('database_settings.sync_now') }}
            </button>
        </div>

        <form wire:submit="saveSync" class="{{ $card }} space-y-4">
            <h2 class="text-base font-semibold text-on-surface dark:text-white flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">schedule</span>
                {{ __('database_settings.auto_sync') }}
            </h2>

            <label class="flex items-center gap-2 text-sm text-on-surface dark:text-white cursor-pointer">
                <input type="checkbox" wire:model="autoSync" class="rounded text-secondary focus:ring-secondary">
                <span>{{ __('database_settings.auto_sync') }}</span>
            </label>

            <div class="max-w-[12rem]">
                <label class="{{ $label }}">{{ __('database_settings.sync_time') }}</label>
                <input type="time" wire:model="syncTime" dir="ltr" class="{{ $input }}">
                @error('syncTime') <p class="text-error text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2 text-sm text-on-surface dark:text-white cursor-pointer">
                <input type="checkbox" wire:model="includeSessions" class="mt-0.5 rounded text-secondary focus:ring-secondary">
                <span>
                    {{ __('database_settings.include_sessions') }}
                    <span class="block text-xs text-on-surface-variant dark:text-white/60">{{ __('database_settings.include_sessions_hint') }}</span>
                </span>
            </label>

            <p class="text-xs text-on-surface-variant dark:text-white/60">{{ __('database_settings.cron_hint') }}</p>

            <button type="submit" class="{{ $btnPrimary }}">
                <span class="material-symbols-outlined text-[18px]">save</span>
                {{ __('database_settings.save_sync') }}
            </button>
        </form>
    </div>

    {{-- ── History ── --}}
    <div class="{{ $card }}">
        <h2 class="text-base font-semibold text-on-surface dark:text-white mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-secondary">history</span>
            {{ __('database_settings.history_title') }}
        </h2>

        @if ($history === [])
            <p class="text-sm text-on-surface-variant dark:text-white/60">{{ __('database_settings.history_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-start text-xs text-on-surface-variant dark:text-white/60 border-b border-surface-container dark:border-[#1a2e42]">
                            <th class="py-2 text-start font-semibold">{{ __('database_settings.col_date') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('database_settings.col_direction') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('database_settings.col_trigger') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('database_settings.col_rows') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('database_settings.col_duration') }}</th>
                            <th class="py-2 text-start font-semibold">{{ __('database_settings.col_result') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history as $entry)
                            @php
                                $started = \Illuminate\Support\Carbon::parse($entry['started_at']);
                                $seconds = $entry['finished_at'] ? (int) $started->diffInSeconds(\Illuminate\Support\Carbon::parse($entry['finished_at'])) : null;
                            @endphp
                            <tr class="border-b border-surface-container/60 dark:border-[#1a2e42]/60 align-top" wire:key="run-{{ $entry['id'] }}">
                                <td class="py-2 whitespace-nowrap" dir="ltr">{{ $started->timezone(config('app.timezone'))->format('Y/m/d H:i') }}</td>
                                <td class="py-2 whitespace-nowrap" dir="ltr">
                                    {{ __('database_settings.names.'.$entry['from']) }} → {{ __('database_settings.names.'.$entry['to']) }}
                                </td>
                                <td class="py-2">{{ __('database_settings.triggers.'.($entry['trigger'] ?? 'console')) }}</td>
                                <td class="py-2">{{ number_format((int) ($entry['rows'] ?? 0)) }}</td>
                                <td class="py-2">{{ $seconds === null ? '—' : __('database_settings.seconds', ['count' => $seconds]) }}</td>
                                <td class="py-2">
                                    @if (($entry['state'] ?? '') === 'succeeded')
                                        <span class="text-emerald-700 dark:text-emerald-300 font-semibold">{{ __('database_settings.states.succeeded') }}</span>
                                        @if (($entry['schema'] ?? null) === 'rebuilt')
                                            <span class="block text-[11px] text-on-surface-variant dark:text-white/60">{{ __('database_settings.schema_rebuilt') }}</span>
                                        @endif
                                    @else
                                        <span class="text-error font-semibold">{{ __('database_settings.states.failed') }}</span>
                                        <span class="block text-[11px] text-error/80 break-all max-w-xs" dir="ltr">{{ $entry['error'] }}</span>
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
