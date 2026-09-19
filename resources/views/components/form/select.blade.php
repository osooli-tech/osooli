@props([
    'name',
    'label',
    'options' => [],
    'required' => false,
    'placeholder' => null,
    'hint' => null,
])

{{-- $options is an ordered map of value => label. A plain list is accepted too,
     in which case the value and the label are the same string.

     The two are told apart with array_is_list(), not by inspecting the key
     type: PHP canonicalises a numeric-string key back to an int, so an
     id => name map is indistinguishable from a list on key type alone and a
     foreign-key dropdown would post the name where the id belongs. --}}
@php
    $optionList = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $optionsAreList = array_is_list($optionList);
@endphp

<div>
    <label for="{{ $name }}"
           class="block text-xs font-medium text-on-surface-variant dark:text-on-primary-container mb-1">
        {{ $label }}
        @if ($required)
            <span class="text-error">*</span>
        @endif
    </label>

    <select id="{{ $name }}"
            wire:model="{{ $name }}"
            {{ $attributes->class([
                'w-full px-3 py-2 text-sm rounded-xl',
                'bg-surface-container dark:bg-[#252b3b]',
                'border border-outline-variant dark:border-white/10',
                'text-on-surface dark:text-white',
                'focus:outline-none focus:ring-2 focus:ring-primary/40',
                'border-error ring-1 ring-error/40' => $errors->has($name),
            ]) }}>
        <option value="">{{ $placeholder ?? __('common.choose') }}</option>
        @foreach ($optionList as $value => $text)
            <option value="{{ $optionsAreList ? $text : $value }}">{{ $text }}</option>
        @endforeach
    </select>

    @if ($hint)
        <p class="mt-1 text-xs text-on-surface-variant dark:text-on-primary-container/70">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-error">{{ $message }}</p>
    @enderror
</div>
