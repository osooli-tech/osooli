@props([
    'name',
    'label',
    // hijri (Umm al-Qura) or gregorian; the value is stored as YYYY-MM-DD in it.
    'calendar' => 'hijri',
    'required' => false,
    'hint' => null,
    'placeholder' => null,
    // Filters apply as soon as a date is picked; form fields wait for save.
    'live' => false,
])

{{-- A date is picked from a calendar, never typed: a typed date is where the
     wrong separator, a swapped month and day or a Gregorian year in a Hijri
     field came from. The field stays read-only and opens the calendar. --}}
<div x-data="datePicker({
        value: $wire.entangle(@js($name), @js((bool) $live)),
        calendar: @js($calendar),
     })"
     @click.outside="close()"
     @keydown.escape="close()"
     class="relative">

    <label for="{{ $name }}"
           class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
        {{ $label }}
        @if ($required)
            <span class="text-error">*</span>
        @endif
    </label>

    <div class="relative">
        <input id="{{ $name }}"
               type="text"
               readonly
               :value="value"
               @click="toggle()"
               @keydown.enter.prevent="toggle()"
               @keydown.space.prevent="toggle()"
               @keydown.arrow-down.prevent="open || toggle()"
               placeholder="{{ $placeholder ?? __('common.pick_date') }}"
               {{ $attributes->class([
                   'w-full ps-9 pe-9 py-2 text-sm rounded-xl cursor-pointer data-tabular ltr text-start',
                   'bg-surface-container dark:bg-[#252b3b]',
                   'border border-outline-variant dark:border-white/10',
                   'text-on-surface dark:text-white placeholder:text-on-surface-variant',
                   'focus:outline-none focus:ring-2 focus:ring-primary/40',
                   'border-error ring-1 ring-error/40' => $errors->has($name),
               ]) }}>

        <span class="material-symbols-outlined absolute top-1/2 -translate-y-1/2 start-3 text-[18px]
                     text-on-surface-variant dark:text-on-primary-container pointer-events-none">calendar_month</span>

        <button type="button" x-show="value" x-cloak @click="clear()"
                class="absolute top-1/2 -translate-y-1/2 end-2 p-1 rounded-lg
                       text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5"
                title="{{ __('common.clear') }}">
            <span class="material-symbols-outlined text-[16px]">close</span>
        </button>
    </div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-50 mt-1 w-72 max-w-[calc(100vw-2rem)] rounded-xl shadow-lg p-3
                bg-surface dark:bg-[#1e2435] border border-outline-variant dark:border-white/10">

        {{-- Month and year --}}
        <div class="flex items-center gap-1 mb-2">
            <button type="button" @click="shift(-1)" title="{{ __('common.previous_month') }}"
                    class="p-1 rounded-lg text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5">
                <span class="material-symbols-outlined text-[18px] rtl:rotate-180">chevron_left</span>
            </button>

            <select x-model.number="viewM"
                    class="flex-1 min-w-0 px-2 py-1 text-sm rounded-lg bg-surface-container dark:bg-[#252b3b]
                           border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                           focus:outline-none focus:ring-2 focus:ring-primary/40">
                <template x-for="month in months" :key="month.value">
                    <option :value="month.value" x-text="month.label" :selected="month.value === viewM"></option>
                </template>
            </select>

            <select x-model.number="viewY"
                    class="w-20 px-2 py-1 text-sm rounded-lg data-tabular bg-surface-container dark:bg-[#252b3b]
                           border border-outline-variant dark:border-white/10 text-on-surface dark:text-white
                           focus:outline-none focus:ring-2 focus:ring-primary/40">
                <template x-for="year in years" :key="year">
                    <option :value="year" x-text="year" :selected="year === viewY"></option>
                </template>
            </select>

            <button type="button" @click="shift(1)" title="{{ __('common.next_month') }}"
                    class="p-1 rounded-lg text-on-surface-variant dark:text-on-primary-container hover:bg-surface-container dark:hover:bg-white/5">
                <span class="material-symbols-outlined text-[18px] rtl:rotate-180">chevron_right</span>
            </button>
        </div>

        {{-- Days --}}
        <div class="grid grid-cols-7 gap-0.5 text-center">
            <template x-for="(weekday, index) in weekdays" :key="index">
                <div class="py-1 text-[11px] font-medium text-on-surface-variant dark:text-on-primary-container" x-text="weekday"></div>
            </template>
            <template x-for="cell in cells" :key="cell.key">
                <div>
                    <template x-if="!cell.blank">
                        <button type="button" @click="pick(cell.day)"
                                :class="cell.selected
                                    ? 'bg-primary text-on-primary font-semibold'
                                    : (cell.today
                                        ? 'ring-1 ring-secondary text-secondary'
                                        : 'text-on-surface dark:text-white hover:bg-secondary/10')"
                                class="w-full aspect-square rounded-lg text-sm data-tabular"
                                x-text="cell.label"></button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-outline-variant dark:border-white/10">
            <span class="text-[11px] text-on-surface-variant dark:text-on-primary-container data-tabular" x-show="equivalent">
                <span>{{ $calendar === 'hijri' ? __('common.gregorian_equivalent') : __('common.hijri_equivalent') }}</span>
                <span dir="ltr" x-text="equivalent"></span>
            </span>
            <button type="button" @click="pickToday()"
                    class="ms-auto px-2 py-1 text-xs font-medium rounded-lg text-secondary hover:bg-secondary/10">
                {{ __('common.today') }}
            </button>
        </div>
    </div>

    @if ($hint)
        <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
