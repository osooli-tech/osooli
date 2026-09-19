@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'hint' => null,
    'ltr' => false,
])

{{-- Label, control and error message in one component: a call site cannot
     forget the error line or style the invalid state differently. --}}
<div>
    <label for="{{ $name }}"
           class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
        {{ $label }}
        @if ($required)
            <span class="text-error">*</span>
        @endif
    </label>

    <input id="{{ $name }}"
           type="{{ $type }}"
           wire:model{{ $attributes->get('live') ? '.live.debounce.400ms' : '' }}="{{ $name }}"
           autocomplete="off"
           {{ $attributes->except(['live'])->class([
               'w-full px-3 py-2 text-sm rounded-xl',
               'bg-surface-container dark:bg-[#252b3b]',
               'border border-outline-variant dark:border-white/10',
               'text-on-surface dark:text-white',
               'focus:outline-none focus:ring-2 focus:ring-primary/40',
               'ltr text-start' => $ltr,
               'border-error ring-1 ring-error/40' => $errors->has($name),
           ]) }} />

    @if ($hint)
        <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
