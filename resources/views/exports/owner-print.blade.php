<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
    {{-- Same foundation as exports/parcel-print.blade.php: @ar() pre-shapes
         Arabic text (dompdf has no bidi/shaping support), and table columns
         are reversed in markup for Arabic — dompdf renders <td>/<th> in
         strict DOM order regardless of dir="rtl", it does not reverse them
         the way a browser does. --}}
    body {
        font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0b1c30; margin: 0;
        direction: {{ app()->isLocale('ar') ? 'rtl' : 'ltr' }};
    }

    .page { padding: 22px 24px; }

    .top-header table { width: 100%; }
    .top-header .logo-mark { width: 42px; height: auto; vertical-align: middle; }
    .top-header .brand-cell { vertical-align: top; }
    .top-header .brand-text { vertical-align: middle; padding-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 10px; }
    .top-header .brand { font-size: 16px; font-weight: bold; color: #002444; }
    .top-header .title-cell { text-align: center; vertical-align: middle; }
    .top-header .title-cell h1 { font-size: 19px; color: #002444; margin: 0 0 3px; }
    .top-header .title-cell .subtitle { font-size: 9.5px; color: #666; margin: 0; }
    .top-header .meta-cell { vertical-align: middle; text-align: {{ app()->isLocale('ar') ? 'left' : 'right' }}; font-size: 9px; color: #555; line-height: 1.7; }
    .top-header .meta-cell .meta-label { color: #888; }

    .report-number {
        display: inline-block; background-color: #006c4e; color: #ffffff;
        font-size: 10px; font-weight: bold; padding: 4px 14px; border-radius: 4px; margin-top: 10px;
    }

    hr.divider { border: none; border-top: 2px solid #002444; margin: 14px 0; }

    .section { margin-bottom: 12px; }
    .section h2 {
        font-size: 11.5px; color: #ffffff; background-color: #002444;
        padding: 6px 10px; margin: 0 0 8px; border-radius: 3px;
    }

    .card { border: 1px solid #e2e2e2; border-radius: 4px; overflow: hidden; }
    .card .card-title {
        background-color: #f2f0ea; color: #002444; font-size: 10px; font-weight: bold;
        padding: 5px 8px; border-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 3px solid #006c4e;
    }
    .card .card-body { padding: 8px; }

    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 3px 0; vertical-align: top; border-bottom: 1px solid #eee; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; font-size: 9.5px; }
    table.kv td.label { color: #777; width: 42%; }
    table.kv td.value { font-weight: bold; color: #0b1c30; }

    .stat-strip { border: 1px solid #e2e2e2; border-radius: 4px; overflow: hidden; }
    .stat-strip table { width: 100%; }
    .stat-strip td { padding: 8px 10px; text-align: center; border-{{ app()->isLocale('ar') ? 'left' : 'right' }}: 1px solid #eee; }
    .stat-strip td:last-child { border: none; }
    .stat-strip td .figure { font-size: 15px; font-weight: bold; color: #006c4e; }
    .stat-strip td .caption { font-size: 8.5px; color: #777; margin-top: 2px; }
    .stat-strip td .note { font-size: 8px; color: #999; margin-top: 3px; }

    table.list { width: 100%; border-collapse: collapse; border: 1px solid #ddd; }
    table.list th, table.list td { border: 1px solid #ddd; padding: 5px 7px; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; font-size: 9.5px; }
    table.list th { background-color: #002444; color: #fff; font-size: 9.5px; }
    table.list tr:nth-child(even) { background-color: #f8f9ff; }

    .footer-bar {
        margin-top: 16px; background-color: #002444; color: #ffffff;
        border-radius: 4px; padding: 8px 14px;
    }
    .footer-bar table { width: 100%; }
    .footer-bar td { font-size: 9px; vertical-align: middle; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; }

    .disclaimer { margin-top: 10px; font-size: 8.5px; color: #888; line-height: 1.6; }
</style>
</head>
<body>

    <div class="page">

        {{-- Header --}}
        <div class="top-header">
            <table>
                <tr>
                    <td class="brand-cell" style="width: 30%;">
                        <table><tr>
                            <td><img class="logo-mark" src="{{ public_path('images/logo-icon.png') }}" alt=""></td>
                            <td class="brand-text"><div class="brand">@ar(__('nav.app_name'))</div></td>
                        </tr></table>
                    </td>
                    <td class="title-cell" style="width: 40%;">
                        <h1>@ar(__('owners.report_title'))</h1>
                        <p class="subtitle">@ar(__('owners.report_subtitle'))</p>
                    </td>
                    <td class="meta-cell" style="width: 30%;">
                        <div><span class="meta-label">@ar(__('parcels.print_generated_at')):</span> <span dir="ltr">{{ now()->format('Y-m-d') }}</span></div>
                        <div><span class="meta-label">@ar(__('parcels.print_report_time')):</span> <span dir="ltr">{{ now()->format('H:i') }}</span></div>
                        <div><span class="meta-label">@ar(__('parcels.print_prepared_by')):</span> @ar(__('parcels.print_prepared_by_value'))</div>
                    </td>
                </tr>
            </table>
            <div style="text-align: center;">
                <span class="report-number">@ar(__('parcels.print_report_number')): <span dir="ltr">{{ $reportNumber }}</span></span>
            </div>
        </div>

        <hr class="divider">

        {{-- Owner info + summary --}}
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
                        <td>
                            <div class="figure" dir="ltr">{{ number_format($summary['parcels_total']) }}</div>
                            <div class="caption">@ar(__('owners.parcel_count'))</div>
                        </td>
                        <td>
                            <div class="figure" dir="ltr">{{ number_format($summary['deeds_active']) }}</div>
                            <div class="caption">@ar(__('dashboard.updated_deeds'))</div>
                        </td>
                        <td>
                            <div class="figure" dir="ltr">{{ number_format($summary['deeds_expired']) }}</div>
                            <div class="caption">@ar(__('dashboard.non_updated_deeds'))</div>
                        </td>
                        <td>
                            <div class="figure" dir="ltr">{{ number_format($summary['cities_count']) }}</div>
                            <div class="caption">@ar(__('owners.cities_count'))</div>
                        </td>
                        <td>
                            <div class="figure" dir="ltr">{{ number_format($summary['area_total_sqm'], 0) }}</div>
                            <div class="caption">@ar(__('owners.total_area_owned')) (@ar(__('dashboard.area_unit_sqm')))</div>
                        </td>
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
                        <td>
                            <div class="figure" dir="ltr">{{ $portfolio['avg_m_price'] !== null ? number_format($portfolio['avg_m_price'], 0) : '—' }}</div>
                            <div class="caption">@ar(__('parcels.m_price')) (@ar(__('parcels.sar')))</div>
                        </td>
                        <td>
                            <div class="figure" dir="ltr">{{ $portfolio['total_value'] !== null ? number_format($portfolio['total_value'], 0) : '—' }}</div>
                            <div class="caption">@ar(__('parcels.parcel_price')) (@ar(__('parcels.sar')))</div>
                            @if ($portfolio['total_value'] !== null && $portfolio['priced_parcels'] < $portfolio['total_parcels'])
                                <div class="note">@ar(__('owners.valuation_coverage_note', ['priced' => $portfolio['priced_parcels'], 'total' => $portfolio['total_parcels']]))</div>
                            @endif
                        </td>
                    </tr></table>
                </div>
            </div>
        @endif

        {{-- Parcels & deeds — every one this owner holds, in full or in share. --}}
        <div class="section" style="margin-top: 12px;">
            <h2>@ar(__('owners.deeds_table_heading'))</h2>
            @if ($deeds->isEmpty())
                <p style="color:#888;">@ar(__('parcels.no_deeds'))</p>
            @else
                <table class="list">
                    <thead>
                        @if (app()->isLocale('ar'))
                            <tr>
                                <th>@ar(__('parcels.deed_status'))</th>
                                <th>@ar(__('parcels.area_deed'))</th>
                                <th>@ar(__('parcels.deed_date'))</th>
                                <th>@ar(__('parcels.deed_no'))</th>
                                <th>@ar(__('parcels.district'))</th>
                                <th>@ar(__('parcels.plan_no'))</th>
                                <th>@ar(__('parcels.parcel_no'))</th>
                            </tr>
                        @else
                            <tr>
                                <th>@ar(__('parcels.parcel_no'))</th>
                                <th>@ar(__('parcels.plan_no'))</th>
                                <th>@ar(__('parcels.district'))</th>
                                <th>@ar(__('parcels.deed_no'))</th>
                                <th>@ar(__('parcels.deed_date'))</th>
                                <th>@ar(__('parcels.area_deed'))</th>
                                <th>@ar(__('parcels.deed_status'))</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @foreach ($deeds as $deed)
                            @php
                                $parcel = $deed->parcel;
                                $district = $parcel?->plan?->district;
                                $districtName = $district
                                    ? (app()->isLocale('ar') ? ($district->name_ar ?? '—') : ($district->name_en ?? '—'))
                                    : '—';
                                $areaLabel = $deed->deed_area
                                    ? number_format((float) $deed->deed_area, 0).' '.__('dashboard.area_unit_sqm')
                                    : '—';
                                $statusLabel = $deed->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—';
                            @endphp
                            @if (app()->isLocale('ar'))
                                <tr>
                                    <td>@ar($statusLabel)</td>
                                    <td>@ar($areaLabel)</td>
                                    <td>@ar($deed->deed_date_hijri ?? '—')</td>
                                    <td>@ar($deed->deed_no ?? '—')</td>
                                    <td>@ar($districtName)</td>
                                    <td>@ar($parcel?->plan?->plan_no ?? '—')</td>
                                    <td>@ar((string) ($parcel?->parcel_no ?? '—'))</td>
                                </tr>
                            @else
                                <tr>
                                    <td>@ar((string) ($parcel?->parcel_no ?? '—'))</td>
                                    <td>@ar($parcel?->plan?->plan_no ?? '—')</td>
                                    <td>@ar($districtName)</td>
                                    <td>@ar($deed->deed_no ?? '—')</td>
                                    <td>@ar($deed->deed_date_hijri ?? '—')</td>
                                    <td>@ar($areaLabel)</td>
                                    <td>@ar($statusLabel)</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <p class="disclaimer">@ar(__('parcels.print_footer_note'))</p>

        {{-- Contact footer --}}
        <div class="footer-bar">
            <table><tr>
                <td style="width: 34%;" dir="ltr">{{ preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')) }}</td>
                <td style="width: 33%;" dir="ltr">{{ config('landing.contact_phone') }}</td>
                <td style="width: 33%;" dir="ltr">{{ config('landing.contact_email') }}</td>
            </tr></table>
        </div>

    </div>

</body>
</html>
