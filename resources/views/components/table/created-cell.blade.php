@props(['date' => null, 'pad' => 'px-4 py-3'])

{{-- A row's date added: the day, with the time underneath. --}}
<td {{ $attributes->class($pad.' text-on-surface-variant dark:text-on-primary-container data-tabular whitespace-nowrap') }}>
    @if ($date)
        @php($date = \Illuminate\Support\Carbon::parse($date))
        <span dir="ltr">{{ $date->format('Y-m-d') }}</span>
        <span class="block text-[11px] opacity-70" dir="ltr">{{ $date->format('H:i') }}</span>
    @else
        —
    @endif
</td>
