@props(['completeness'])

{{-- One tile per tracked field: lit when recorded, dashed when missing.
     The pattern alone tells the owner how complete the file is. --}}
@php $missing = $completeness['missing']->all(); @endphp
<div {{ $attributes->merge(['class' => 'grid grid-cols-3 sm:grid-cols-5 gap-2']) }}>
    @foreach ($completeness['fields'] as $field)
        @php $has = ! in_array($field, $missing, true); @endphp
        <div class="relative rounded-xl p-2.5 text-center transition-transform hover:scale-[1.04]
                    {{ $has
                        ? 'bg-gradient-to-br from-secondary to-on-secondary-fixed-variant text-white shadow-sm'
                        : 'border-2 border-dashed border-outline-variant dark:border-white/15 text-on-surface-variant dark:text-on-primary-container' }}">
            <span class="material-symbols-outlined text-[18px] {{ $has ? 'text-secondary-fixed' : 'opacity-60' }}">{{ $has ? 'check_circle' : 'radio_button_unchecked' }}</span>
            <p class="text-[11px] leading-tight mt-1">{{ __('parcels.'.$field) }}</p>
        </div>
    @endforeach
</div>
