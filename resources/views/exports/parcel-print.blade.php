<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
    {{-- dompdf shapes nothing itself: every Arabic string below goes through
         @ar(), which pre-shapes it into visual glyph order and marks it
         dir="ltr" so dompdf renders that order as-is rather than reversing it
         again. The page itself stays dir=rtl so the layout (alignment,
         column order) still reads right-to-left. --}}
    body {
        font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0b1c30; margin: 0;
        direction: {{ app()->isLocale('ar') ? 'rtl' : 'ltr' }};
    }
    table { direction: {{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}; }

    .page { padding: 22px 24px; }

    {{-- Top header: brand on one side, report meta on the other — a table
         row rather than flex, which dompdf does not lay out reliably. --}}
    .top-header table { width: 100%; }
    .top-header .logo-mark { width: 42px; height: auto; vertical-align: middle; }
    .top-header .brand-cell { vertical-align: top; }
    .top-header .brand-text { vertical-align: middle; padding-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 10px; }
    .top-header .brand { font-size: 16px; font-weight: bold; color: #002444; }
    .top-header .tagline { font-size: 9px; color: #666; margin-top: 2px; }
    .top-header .title-cell { text-align: center; vertical-align: middle; }
    .top-header .title-cell h1 { font-size: 19px; color: #002444; margin: 0 0 3px; }
    .top-header .title-cell .subtitle { font-size: 9.5px; color: #666; margin: 0; }
    .top-header .meta-cell { vertical-align: middle; text-align: {{ app()->isLocale('ar') ? 'left' : 'right' }}; font-size: 9px; color: #555; line-height: 1.7; }
    .top-header .meta-cell .meta-label { color: #888; }

    .report-number {
        display: inline-block;
        background-color: #006c4e;
        color: #ffffff;
        font-size: 10px;
        font-weight: bold;
        padding: 4px 14px;
        border-radius: 4px;
        margin-top: 10px;
    }

    hr.divider { border: none; border-top: 2px solid #002444; margin: 14px 0; }

    .section { margin-bottom: 12px; }
    .section h2 {
        font-size: 11.5px;
        color: #ffffff;
        background-color: #002444;
        padding: 6px 10px;
        margin: 0 0 8px;
        border-radius: 3px;
    }

    .cards-row table.layout { width: 100%; }
    .cards-row table.layout > tr > td { vertical-align: top; padding: 0 5px; width: 33.33%; }
    .cards-row table.layout > tr > td:first-child { padding-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 0; }
    .cards-row table.layout > tr > td:last-child { padding-{{ app()->isLocale('ar') ? 'left' : 'right' }}: 0; }

    .card {
        border: 1px solid #e2e2e2;
        border-radius: 4px;
        overflow: hidden;
    }
    .card .card-title {
        background-color: #f2f0ea;
        color: #002444;
        font-size: 10px;
        font-weight: bold;
        padding: 5px 8px;
        border-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 3px solid #006c4e;
    }
    .card .card-body { padding: 8px; }
    .card img.card-image { width: 100%; display: block; }
    .card .placeholder { color: #999; font-size: 9.5px; text-align: center; padding: 20px 6px; }

    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 3px 0; vertical-align: top; border-bottom: 1px solid #eee; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; font-size: 9.5px; }
    table.kv td.label { color: #777; width: 42%; }
    table.kv td.value { font-weight: bold; color: #0b1c30; }

    table.list { width: 100%; border-collapse: collapse; border: 1px solid #ddd; }
    table.list th, table.list td { border: 1px solid #ddd; padding: 5px 7px; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; font-size: 9.5px; }
    table.list th { background-color: #002444; color: #fff; font-size: 9.5px; }
    table.list tr:nth-child(even) { background-color: #f8f9ff; }

    .valuation-strip { border: 1px solid #e2e2e2; border-radius: 4px; overflow: hidden; }
    .valuation-strip table { width: 100%; }
    .valuation-strip td { padding: 8px 12px; text-align: center; }
    .valuation-strip td.figure { font-size: 15px; font-weight: bold; color: #006c4e; }
    .valuation-strip td .caption { font-size: 9px; color: #777; margin-top: 2px; }
    .valuation-strip td.note { font-size: 8px; color: #999; }

    .doc-block { margin-bottom: 16px; }
    .doc-block .doc-title { font-size: 10.5px; font-weight: bold; color: #002444; margin-bottom: 6px; }
    .doc-block img { max-width: 100%; border: 1px solid #ddd; }
    .doc-block .missing { color: #999; font-size: 9px; }

    .footer-bar {
        margin-top: 16px;
        background-color: #002444;
        color: #ffffff;
        border-radius: 4px;
        padding: 8px 14px;
    }
    .footer-bar table { width: 100%; }
    .footer-bar td { font-size: 9px; vertical-align: middle; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; }

    {{-- Float, not a table cell: an inline <svg> placed inside a dompdf table
         cell was measured at zero size repeatedly (a table-layout quirk),
         while this exact float pattern was already proven to work in the
         report's title-row QR box. --}}
    .qr-strip { margin-top: 10px; overflow: hidden; }
    .qr-strip .qr-box { float: {{ app()->isLocale('ar') ? 'right' : 'left' }}; text-align: center; width: 78px; }
    .qr-strip .qr-box img, .qr-strip .qr-box svg { width: 78px; height: 78px; }
    .qr-strip .qr-box p { font-size: 8px; color: #777; margin: 3px 0 0; }
    .qr-strip .disclaimer {
        margin-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 90px;
        font-size: 8.5px; color: #888; line-height: 1.6;
    }
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
                            <td class="brand-text">
                                <div class="brand">@ar(__('nav.app_name'))</div>
                            </td>
                        </tr></table>
                    </td>
                    <td class="title-cell" style="width: 40%;">
                        <h1>@ar(__('parcels.print_report_title'))</h1>
                        <p class="subtitle">@ar(__('parcels.print_report_subtitle'))</p>
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

        {{-- Map / Info / Photo+Coordinates — three cards side by side --}}
        <div class="cards-row">
            <table class="layout"><tr>

                <td>
                    <div class="card">
                        <div class="card-title">@ar(__('parcels.map_section'))</div>
                        <div class="card-body" style="padding: 0;">
                            @if ($mapImage)
                                <img class="card-image" src="{{ $mapImage }}">
                            @else
                                <p class="placeholder">@ar(__('parcels.map_not_rendered'))</p>
                            @endif
                        </div>
                    </div>
                </td>

                <td>
                    <div class="card">
                        <div class="card-title">@ar(__('parcels.parcel_info'))</div>
                        <div class="card-body">
                            <table class="kv">
                                <x-print.kv-row :label="__('parcels.deed_no')" :value="$parcel->currentDeed->deed_no ?? '—'" />
                                <x-print.kv-row :label="__('parcels.plan_no')" :value="$twin['identity']['plan_no'] ?? '—'" />
                                <x-print.kv-row :label="__('parcels.parcel_no')" :value="(string) $parcel->parcel_no" />
                                <x-print.kv-row :label="__('parcels.district')" :value="$twin['identity']['district'] ?? '—'" />
                                <x-print.kv-row :label="__('parcels.city')" :value="$twin['identity']['city'] ?? '—'" />
                                <x-print.kv-row :label="__('parcels.area_deed')" :value="$twin['area']['deed'] ? number_format($twin['area']['deed'], 0).' '.__('dashboard.area_unit_sqm') : '—'" />
                                <x-print.kv-row :label="__('parcels.asset_type')" :value="$twin['identity']['asset_type'] ? __('parcels.asset_types.'.$twin['identity']['asset_type']) : '—'" />
                                <x-print.kv-row :label="__('parcels.land_transaction')" :value="$twin['identity']['land_transaction'] ? __('parcels.land_transactions.'.$twin['identity']['land_transaction']) : '—'" />
                                <x-print.kv-row :label="__('parcels.deed_status')" :value="$parcel->currentDeed?->deed_status ? __('parcels.deed_statuses.'.$parcel->currentDeed->deed_status) : '—'" />
                                <x-print.kv-row :label="__('parcels.deed_date')" :value="$parcel->currentDeed->deed_date_hijri ?? '—'" />
                            </table>
                        </div>
                    </div>
                </td>

                <td>
                    <div class="card" style="margin-bottom: 8px;">
                        <div class="card-title">@ar(__('parcels.site_photo_section'))</div>
                        <div class="card-body" style="padding: 0;">
                            @if ($sitePhoto)
                                <img class="card-image" src="{{ $sitePhoto }}">
                            @else
                                <p class="placeholder">@ar(__('parcels.no_site_photo'))</p>
                            @endif
                        </div>
                    </div>
                    @if ($centroid)
                        <div class="card">
                            <div class="card-title">@ar(__('parcels.coordinates_section'))</div>
                            <div class="card-body">
                                <p dir="ltr" style="text-align: center; font-weight: bold; margin: 0;" class="data-tabular">
                                    {{ number_format($centroid['lat'], 6) }}, {{ number_format($centroid['lng'], 6) }}
                                </p>
                            </div>
                        </div>
                    @endif
                </td>

            </tr></table>
        </div>

        {{-- Valuation — real, currently-recorded figures only; no trend or forecast. --}}
        @if ($twin['valuation']['per_metre'] || $twin['valuation']['total'])
            <div class="section" style="margin-top: 12px;">
                <h2>@ar(__('parcels.valuation_section'))</h2>
                <div class="valuation-strip">
                    <table><tr>
                        <td style="border-{{ app()->isLocale('ar') ? 'left' : 'right' }}: 1px solid #eee;">
                            <div class="figure" dir="ltr">{{ $twin['valuation']['per_metre'] ? number_format($twin['valuation']['per_metre'], 0) : '—' }}</div>
                            <div class="caption">@ar(__('parcels.m_price')) (@ar(__('parcels.sar')))</div>
                        </td>
                        <td>
                            <div class="figure" dir="ltr">{{ $twin['valuation']['total'] ? number_format($twin['valuation']['total'], 0) : '—' }}</div>
                            <div class="caption">@ar(__('parcels.parcel_price')) (@ar(__('parcels.sar')))</div>
                            @if ($twin['valuation']['is_derived'])
                                <div class="note">@ar(__('parcels.valuation_derived_note'))</div>
                            @endif
                        </td>
                    </tr></table>
                </div>
            </div>
        @endif

        {{-- Deeds — a parcel may carry more than one, so every one is listed. --}}
        <div class="section" style="margin-top: 12px;">
            <h2>@ar(__('parcels.deeds_section'))</h2>
            @if ($parcel->deeds->isEmpty())
                <p style="color:#888;">@ar(__('parcels.no_deeds'))</p>
            @else
                <table class="list">
                    <thead>
                        {{-- dompdf renders <th>/<td> in strict DOM order regardless
                             of dir="rtl" — columns are reversed here in markup so
                             "رقم الصك" (the primary column) still lands on the
                             right, where Arabic reading starts. --}}
                        @if (app()->isLocale('ar'))
                            <tr>
                                <th>@ar(__('parcels.deed_class'))</th>
                                <th>@ar(__('parcels.deed_status'))</th>
                                <th>@ar(__('parcels.deed_date'))</th>
                                <th>@ar(__('parcels.deed_no'))</th>
                            </tr>
                        @else
                            <tr>
                                <th>@ar(__('parcels.deed_no'))</th>
                                <th>@ar(__('parcels.deed_date'))</th>
                                <th>@ar(__('parcels.deed_status'))</th>
                                <th>@ar(__('parcels.deed_class'))</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        {{-- @ar() shapes unconditionally regardless of locale:
                             a value can be Arabic-origin data (deed_class has
                             no translation table at all) even on an
                             English-locale page, and shaping no-ops on a
                             pure-Latin string. --}}
                        @foreach ($parcel->deeds as $deed)
                            @php
                                $deedStatusLabel = $deed->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—';
                            @endphp
                            @if (app()->isLocale('ar'))
                                <tr>
                                    <td>@ar($deed->deed_class ?? '—')</td>
                                    <td>@ar($deedStatusLabel)</td>
                                    <td>@ar($deed->deed_date_hijri ?? '—')</td>
                                    <td>@ar($deed->deed_no ?? '—')</td>
                                </tr>
                            @else
                                <tr>
                                    <td>@ar($deed->deed_no ?? '—')</td>
                                    <td>@ar($deed->deed_date_hijri ?? '—')</td>
                                    <td>@ar($deedStatusLabel)</td>
                                    <td>@ar($deed->deed_class ?? '—')</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Owners — always a list: co-ownership is common, never a single value. --}}
        <div class="section">
            <h2>@ar(__('parcels.owners_section'))</h2>
            @if (empty($twin['owners']))
                <p style="color:#888;">@ar(__('parcels.not_recorded'))</p>
            @else
                <table class="list">
                    <thead>
                        @if (app()->isLocale('ar'))
                            <tr>
                                <th>@ar(__('parcels.ownership_share'))</th>
                                <th>@ar(__('owners.national_id'))</th>
                                <th>@ar(__('owners.name'))</th>
                            </tr>
                        @else
                            <tr>
                                <th>@ar(__('owners.name'))</th>
                                <th>@ar(__('owners.national_id'))</th>
                                <th>@ar(__('parcels.ownership_share'))</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @foreach ($twin['owners'] as $owner)
                            @php $shareLabel = $owner['share'] !== null ? $owner['share'].'%' : '—'; @endphp
                            @if (app()->isLocale('ar'))
                                <tr>
                                    <td>@ar($shareLabel)</td>
                                    <td>@ar($owner['national_id'] ?? '—')</td>
                                    <td>@ar($owner['name'])</td>
                                </tr>
                            @else
                                <tr>
                                    <td>@ar($owner['name'])</td>
                                    <td>@ar($owner['national_id'] ?? '—')</td>
                                    <td>@ar($shareLabel)</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Boundaries --}}
        @if ($parcel->boundary)
            @php $b = $parcel->boundary; @endphp
            <div class="section">
                <h2>@ar(__('parcels.boundary_section'))</h2>
                {{-- One direction per row, not two side by side: a border
                     value is often a short phrase ("شارع عرض 15 م"), and a
                     narrow column wraps it into a scramble of stacked words. --}}
                <table class="kv">
                    <x-print.kv-row :label="__('parcels.n_border')" :value="$b->n_border ?? '—'" />
                    <x-print.kv-row :label="__('parcels.s_border')" :value="$b->s_border ?? '—'" />
                    <x-print.kv-row :label="__('parcels.e_border')" :value="$b->e_border ?? '—'" />
                    <x-print.kv-row :label="__('parcels.w_border')" :value="$b->w_border ?? '—'" />
                </table>
            </div>
        @endif

        {{-- QR + disclaimer --}}
        <div class="qr-strip">
            <div class="qr-box">
                @if ($qrImage)
                    <img src="{{ $qrImage }}">
                @endif
                <p dir="ltr">{{ $twin['identity']['spatial_id'] ?? $parcel->geo_id }}</p>
            </div>
            <p class="disclaimer">@ar(__('parcels.print_footer_note'))</p>
        </div>

        {{-- Contact footer --}}
        <div class="footer-bar">
            <table><tr>
                <td style="width: 34%;" dir="ltr">{{ preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')) }}</td>
                <td style="width: 33%;" dir="ltr">{{ config('landing.contact_phone') }}</td>
                <td style="width: 33%;" dir="ltr">{{ config('landing.contact_email') }}</td>
            </tr></table>
        </div>

    </div>

    {{-- Documents — each stored file is either a PDF (its first page is
         rasterised server-side) or an image (embedded as-is). dompdf cannot
         embed one PDF's pages inside another, hence the rasterising. --}}
    @if ($documents->isNotEmpty())
        <div class="page" style="page-break-before: always;">
            <div class="section">
                <h2>@ar(__('parcels.documents_page_title'))</h2>
                @foreach ($documents as $doc)
                    <div class="doc-block" style="{{ ! $loop->first ? 'page-break-before: always;' : '' }}">
                        <p class="doc-title">@ar($doc['photo']->photo_type?->value ?? '—')</p>
                        @if ($doc['preview'])
                            <img src="{{ $doc['preview'] }}">
                        @else
                            <p class="missing">@ar(__('parcels.document_not_rendered'))</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</body>
</html>
