@props([
    // The component's $createdSort: '', 'asc' or 'desc'.
    'sort' => '',
    'label' => null,
    'pad' => 'px-4 py-3',
])

{{-- The "date added" column header; a click sorts by it and a second flips the order. --}}
<th {{ $attributes->class($pad.' text-start font-semibold text-on-surface-variant dark:text-on-primary-container whitespace-nowrap') }}
    @if ($sort !== '') aria-sort="{{ $sort === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <button type="button" wire:click="sortByCreated"
            title="{{ __('common.sort_by_date') }}"
            class="inline-flex items-center gap-1 hover:text-primary transition-colors {{ $sort !== '' ? 'text-primary' : '' }}">
        {{ $label ?? __('common.created_at') }}
        <span class="material-symbols-outlined text-[16px]">
            {{ match ($sort) { 'asc' => 'arrow_upward', 'desc' => 'arrow_downward', default => 'unfold_more' } }}
        </span>
    </button>
</th>
