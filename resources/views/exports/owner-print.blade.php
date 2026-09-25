@php
    $isAr = app()->isLocale('ar');

    /*
     * dompdf lays table cells out in DOM order whatever dir="rtl" says; it
     * does not mirror a row the way a browser does. So every row of cells is
     * written in reading order and reversed here for Arabic, which puts the
     * first cell on the right.
     */
    $inReadingOrder = static fn (array $cells): array => $isAr ? array_reverse($cells) : $cells;

    // "Label: value" as one shaped run: shaping the whole phrase keeps the
    // label on the right and the number or date after it, in Arabic order.
    $labelled = static fn (string $label, string $value): string => \App\Support\PdfArabicText::render($label.': '.$value);

    $summaryCells = $inReadingOrder([
        ['figure' => number_format($summary['parcels_total']), 'caption' => __('owners.parcel_count')],
        ['figure' => number_format($summary['deeds_active']), 'caption' => __('dashboard.updated_deeds')],
        ['figure' => number_format($summary['deeds_expired']), 'caption' => __('dashboard.non_updated_deeds')],
        ['figure' => number_format($summary['cities_count']), 'caption' => __('owners.cities_count')],
        ['figure' => number_format($summary['area_total_sqm'], 0), 'caption' => __('owners.total_area_owned').' ('.__('dashboard.area_unit_sqm').')'],
    ]);

    $valuationCells = $inReadingOrder([
        [
            'figure' => $portfolio['avg_m_price'] !== null ? number_format($portfolio['avg_m_price'], 0) : '—',
            'caption' => __('parcels.m_price').' ('.__('parcels.sar').')',
        ],
        [
            'figure' => $portfolio['total_value'] !== null ? number_format($portfolio['total_value'], 0) : '—',
            'caption' => __('parcels.parcel_price').' ('.__('parcels.sar').')',
            'note' => $portfolio['total_value'] !== null && $portfolio['priced_parcels'] < $portfolio['total_parcels']
                ? __('owners.valuation_coverage_note', ['priced' => $portfolio['priced_parcels'], 'total' => $portfolio['total_parcels']])
                : null,
        ],
    ]);

    // The parcel report's disclaimer, less its last sentence, which points
    // at a QR code this report does not carry.
    $disclaimer = array_slice((array) __('parcels.print_footer_lines'), 0, 3);
@endphp
<!DOCTYPE html>
<html dir="{{ $isAr ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
    {{-- Same foundation as exports/parcel-print.blade.php: @ar() pre-shapes
         Arabic text (dompdf has no bidi or letter shaping of its own), and
         every row of cells is reversed for Arabic in the markup above. --}}
    @font-face {
        font-family: 'Tajawal';
        src: url('{{ resource_path('fonts/tajawal/Tajawal-Regular.ttf') }}');
        font-weight: normal;
        font-style: normal;
    }
    @font-face {
        font-family: 'Tajawal';
        src: url('{{ resource_path('fonts/tajawal/Tajawal-Bold.ttf') }}');
        font-weight: bold;
        font-style: normal;
    }
    body {
        font-family: 'Tajawal', 'DejaVu Sans', sans-serif; font-size: 11px; color: #0b1c30; margin: 0;
        direction: {{ $isAr ? 'rtl' : 'ltr' }};
    }

    .page { padding: 22px 24px; }

    .top-header table { width: 100%; }
    .top-header .logo-mark { width: 42px; height: auto; vertical-align: middle; }
    .top-header .brand-cell { vertical-align: middle; }
    .top-header .brand-text { vertical-align: middle; padding-{{ $isAr ? 'right' : 'left' }}: 10px; }
    .top-header .brand { font-size: 17px; font-weight: bold; color: #002444; }
    .top-header .title-cell { text-align: center; vertical-align: middle; }
    .top-header .title-cell h1 { font-size: 20px; font-weight: bold; color: #002444; margin: 0 0 3px; }
    .top-header .title-cell .subtitle { font-size: 10px; color: #666; margin: 0; }
    .top-header .meta-cell { vertical-align: middle; text-align: {{ $isAr ? 'left' : 'right' }}; font-size: 9.5px; color: #555; line-height: 1.8; }

    .report-number {
        display: inline-block; background-color: #006c4e; color: #ffffff;
        font-size: 10.5px; font-weight: bold; padding: 4px 14px; border-radius: 4px; margin-top: 10px;
    }

    hr.divider { border: none; border-top: 2px solid #002444; margin: 14px 0; }

    .section { margin-bottom: 12px; }
    .section h2 {
        font-size: 12px; font-weight: bold; color: #ffffff; background-color: #002444;
        padding: 6px 10px; margin: 0 0 8px; border-radius: 3px; text-align: {{ $isAr ? 'right' : 'left' }};
    }

    .card { border: 1px solid #e2e2e2; border-radius: 4px; overflow: hidden; }
    .card .card-title {
        background-color: #f2f0ea; color: #002444; font-size: 10.5px; font-weight: bold;
        padding: 5px 8px; border-{{ $isAr ? 'right' : 'left' }}: 3px solid #006c4e; text-align: {{ $isAr ? 'right' : 'left' }};
    }
    .card .card-body { padding: 8px; }

    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 3px 0; vertical-align: top; border-bottom: 1px solid #eee; text-align: {{ $isAr ? 'right' : 'left' }}; font-size: 10px; }
    table.kv td.label { color: #777; width: 42%; }
    table.kv td.value { font-weight: bold; color: #0b1c30; }

    .stat-strip { border: 1px solid #e2e2e2; border-radius: 4px; overflow: hidden; }
    .stat-strip table { width: 100%; }
    .stat-strip td { padding: 8px 10px; text-align: center; border-{{ $isAr ? 'left' : 'right' }}: 1px solid #eee; }
    .stat-strip td:last-child { border: none; }
    .stat-strip td .figure { font-size: 16px; font-weight: bold; color: #006c4e; }
    .stat-strip td .caption { font-size: 9px; color: #777; margin-top: 2px; }
    .stat-strip td .note { font-size: 8px; color: #999; margin-top: 3px; }

    table.list { width: 100%; border-collapse: collapse; border: 1px solid #ddd; }
    table.list th, table.list td { border: 1px solid #ddd; padding: 5px 7px; text-align: {{ $isAr ? 'right' : 'left' }}; font-size: 10px; }
    table.list th { background-color: #002444; color: #fff; font-weight: bold; }
    table.list tr:nth-child(even) { background-color: #f8f9ff; }

    .footer-bar {
        margin-top: 16px; background-color: #002444; color: #ffffff;
        border-radius: 4px; padding: 8px 14px;
    }
    .footer-bar table { width: 100%; }
    .footer-bar td { font-size: 9.5px; vertical-align: middle; text-align: center; }

    .disclaimer { margin-top: 10px; text-align: {{ $isAr ? 'right' : 'left' }}; }
    .disclaimer p { font-size: 8.5px; color: #888; margin: 0 0 3px; }
</style>
</head>
<body>

    <div class="page">

        {{-- Header: brand, title, report details — reading order. --}}
        <div class="top-header">
            <table>
                <tr>
                    @foreach ($inReadingOrder(['brand', 'title', 'meta']) as $cell)
                        @if ($cell === 'brand')
                            <td class="brand-cell" style="width: 30%;">
                                <table><tr>
                                    @foreach ($inReadingOrder(['logo', 'name']) as $part)
                                        @if ($part === 'logo')
                                            <td><img class="logo-mark" src="{{ public_path('images/logo-icon.png') }}" alt=""></td>
                                        @else
                                            <td class="brand-text"><div class="brand">@ar(__('nav.app_name'))</div></td>
                                        @endif
                                    @endforeach
                                </tr></table>
                            </td>
                        @elseif ($cell === 'title')
                            <td class="title-cell" style="width: 40%;">
                                <h1>@ar(__('owners.report_title'))</h1>
                                <p class="subtitle">@ar(__('owners.report_subtitle'))</p>
                            </td>
                        @else
                            <td class="meta-cell" style="width: 30%;">
                                <div>{!! $labelled(__('parcels.print_generated_at'), now()->format('Y-m-d')) !!}</div>
                                <div>{!! $labelled(__('parcels.print_report_time'), now()->format('H:i')) !!}</div>
                                <div>{!! $labelled(__('parcels.print_prepared_by'), __('parcels.print_prepared_by_value')) !!}</div>
                            </td>
                        @endif
                    @endforeach
                </tr>
            </table>
            <div style="text-align: center;">
                <span class="report-number">{!! $labelled(__('parcels.print_report_number'), (string) $reportNumber) !!}</span>
            </div>
        </div>

        <hr class="divider">

        {{-- Full-width, not a two-column row: a long Saudi name (4-6 words)
             wraps inside a narrower column, and wrapping an already-shaped
             RTL string scrambles it across lines — the fix is width, not a
             narrower font or truncation. --}}
        <div class="card">
            <div class="card-title">@ar(__('owners.owner_info_section'))</div>
            <div class="card-body">
                <table class="kv">
                    <x-print.kv-row :label="__('owners.name')" :value="$owner->name" />
                    <x-print.kv-row :label="__('owners.national_id')" :value="$owner->national_id ?? '—'" />
                    <x-print.kv-row :label="__('owners.phone')" :value="$owner->phone ?? '—'" />
                    <x-print.kv-row :label="__('owners.email')" :value="$owner->email ?? '—'" />
                </table>
            </div>
        </div>

        <div class="card" style="margin-top: 10px;">
            <div class="card-title">@ar(__('owners.summary_section'))</div>
            <div class="card-body" style="padding: 0;">
                <div class="stat-strip">
                    <table><tr>
                        @foreach ($summaryCells as $stat)
                            <td>
                                <div class="figure" dir="ltr">{{ $stat['figure'] }}</div>
                                <div class="caption">@ar($stat['caption'])</div>
                            </td>
                        @endforeach
                    </tr></table>
                </div>
            </div>
        </div>

        {{-- Valuation — real, currently-recorded figures only; weighted by
             ownership share, same as the mobile app's own dashboard. --}}
        @if ($portfolio['total_value'] !== null || $portfolio['avg_m_price'] !== null)
            <div class="section" style="margin-top: 12px;">
                <h2>@ar(__('owners.valuation_section'))</h2>
                <div class="stat-strip">
                    <table><tr>
                        @foreach ($valuationCells as $stat)
                            <td>
                                <div class="figure" dir="ltr">{{ $stat['figure'] }}</div>
                                <div class="caption">@ar($stat['caption'])</div>
                                @if (! empty($stat['note']))
                                    <div class="note">@ar($stat['note'])</div>
                                @endif
                            </td>
                        @endforeach
                    </tr></table>
                </div>
            </div>
        @endif

        {{-- Parcels & deeds — every one this owner holds, in full or in share. --}}
        <div class="section" style="margin-top: 12px;">
            <h2>@ar(__('owners.deeds_table_heading'))</h2>
            @if ($deeds->isEmpty())
                <p style="color:#888; text-align: {{ $isAr ? 'right' : 'left' }};">@ar(__('parcels.no_deeds'))</p>
            @else
                <table class="list">
                    <thead>
                        <tr>
                            @foreach ($inReadingOrder([
                                __('parcels.parcel_no'), __('parcels.plan_no'), __('parcels.district'), __('parcels.deed_no'),
                                __('parcels.deed_date'), __('parcels.area_deed'), __('parcels.deed_status'),
                            ]) as $heading)
                                <th>@ar($heading)</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deeds as $deed)
                            @php
                                $parcel = $deed->parcel;
                                $district = $parcel?->plan?->district;
                                $districtName = $district
                                    ? ($isAr ? ($district->name_ar ?? '—') : ($district->name_en ?? $district->name_ar ?? '—'))
                                    : '—';
                                $areaLabel = $deed->deed_area
                                    ? number_format((float) $deed->deed_area, 0).' '.__('dashboard.area_unit_sqm')
                                    : '—';
                                $statusLabel = $deed->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—';
                            @endphp
                            <tr>
                                @foreach ($inReadingOrder([
                                    (string) ($parcel?->parcel_no ?? '—'), (string) ($parcel?->plan?->plan_no ?? '—'), $districtName,
                                    (string) ($deed->deed_no ?? '—'), (string) ($deed->deed_date_hijri ?? '—'), $areaLabel, $statusLabel,
                                ]) as $value)
                                    <td>@ar($value)</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- One shaped line per sentence: dompdf wrapping an already-shaped
             paragraph puts its lines in the wrong order. --}}
        <div class="disclaimer">
            @foreach ($disclaimer as $line)
                <p>@ar($line)</p>
            @endforeach
        </div>

        {{-- Contact footer --}}
        <div class="footer-bar">
            <table><tr>
                @foreach ($inReadingOrder([
                    preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')),
                    (string) config('landing.contact_phone'),
                    (string) config('landing.contact_email'),
                ]) as $contact)
                    <td style="width: 33%;" dir="ltr">{{ $contact }}</td>
                @endforeach
            </tr></table>
        </div>

    </div>

</body>
</html>
