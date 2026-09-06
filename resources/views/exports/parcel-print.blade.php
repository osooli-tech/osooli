<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
<style>
    {{-- dompdf shapes nothing itself: every Arabic string below goes through
         @ar(), which pre-shapes it into visual glyph order and marks it
         dir="ltr" so dompdf renders that order as-is rather than reversing it
         again. The page itself stays dir=rtl so the layout (alignment,
         column order) still reads right-to-left.

         This report's layout mirrors the client's approved one-page mock-up
         one for one: three cards, three cards, one full-width map, footer —
         nothing added or dropped, only real data standing in for the
         mock-up's placeholder values. --}}
    body {
        font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #0b1c30; margin: 0;
        direction: {{ app()->isLocale('ar') ? 'rtl' : 'ltr' }};
    }
    table { direction: {{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}; }

    .page { padding: 16px 20px; }

    {{-- Top header --}}
    .top-header { border: 1px solid #e2e2e2; border-radius: 6px; padding: 10px 14px; }
    .top-header table { width: 100%; }
    .top-header .logo-mark { width: 46px; height: auto; vertical-align: middle; }
    .top-header .brand-cell { vertical-align: middle; }
    .top-header .brand-text { vertical-align: middle; padding-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 8px; }
    .top-header .brand { font-size: 17px; font-weight: bold; color: #002444; }
    .top-header .tagline { font-size: 8px; color: #888; margin-top: 1px; }
    .top-header .title-cell { text-align: center; vertical-align: middle; }
    .top-header .title-cell h1 { font-size: 18px; color: #002444; margin: 0 0 3px; }
    .top-header .title-cell .subtitle { font-size: 9px; color: #666; margin: 0 0 6px; }
    .top-header .meta-cell { vertical-align: middle; width: 27%; }
    .meta-box {
        border: 1px solid #e2e2e2; border-radius: 6px; padding: 4px 10px;
        text-align: center; margin-bottom: 5px; font-size: 8.5px; color: #888;
    }
    .meta-box .meta-value { font-size: 10px; font-weight: bold; color: #002444; margin-top: 1px; }

    .report-number {
        display: inline-block;
        background-color: #006c4e;
        color: #ffffff;
        font-size: 9.5px;
        font-weight: bold;
        padding: 3px 14px;
        border-radius: 4px;
    }

    .section { margin-top: 10px; }

    {{-- The three-card rows: equal width AND equal height. dompdf has no
         flexbox, and a height:100% div nested in a table cell does not just
         fail to stretch here — it drove dompdf into generating 11 pages of
         blank output. Table cells themselves DO stretch to the row's tallest
         cell reliably, so the card border lives directly on the <td> (via
         border-spacing for the gutter between cards, not padding+margin
         tricks) instead of on a div wrapped inside it. --}}
    {{-- Descendant selector, not a direct-child one: the markup below writes
         <table><tr> with no explicit <tbody>, and dompdf (like a browser)
         inserts one implicitly, which a `table > tr > td` combinator does not
         see through. That mismatch was silently dropping vertical-align:top,
         leaving cells at the default "middle" — the shorter cards centred
         instead of flushing to the row's top, which is what actually caused
         the misaligned headers. --}}
    .cards-row table.layout { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px; }
    .cards-row table.layout td { vertical-align: top; width: 33.33%; border: 1px solid #e2e2e2; padding: 0; }

    {{-- Standalone cards outside the equal-height rows (the full-width map)
         still use a div wrapper — safe here since there is no sibling cell
         height to match. --}}
    .card { border: 1px solid #e2e2e2; overflow: hidden; }

    .card-title {
        background-color: #002444;
        color: #ffffff;
        font-size: 10px;
        font-weight: bold;
        padding: 6px 8px;
        text-align: center;
    }
    .card-body { padding: 7px; }
    img.card-image { width: 100%; height: 118px; object-fit: cover; display: block; }
    .placeholder { color: #999; font-size: 9px; text-align: center; padding: 18px 6px; }

    .coords-line { padding: 6px 7px 2px; font-size: 8.5px; color: #777; }
    .coords-line .value { display: block; font-weight: bold; color: #0b1c30; margin-top: 2px; }

    table.kv { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 2.5px 0; vertical-align: top; border-bottom: 1px solid #eee; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; font-size: 9px; }
    table.kv td.label { color: #777; width: 46%; }
    table.kv td.value { font-weight: bold; color: #0b1c30; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th, table.grid td { border: 1px solid #e2e2e2; padding: 4px 6px; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; font-size: 8.5px; }
    table.grid th { background-color: #002444; color: #fff; }
    table.grid tr:nth-child(even) td { background-color: #f8f9ff; }
    table.grid td.total-label { font-weight: bold; text-align: center; }
    table.grid td.total-value { font-weight: bold; text-align: center; }
    .geodetic-note { font-size: 7.5px; color: #999; padding: 5px 7px 0; line-height: 1.5; }

    .doc-block { margin-bottom: 16px; }
    .doc-block .doc-title { font-size: 10.5px; font-weight: bold; color: #002444; margin-bottom: 6px; }
    .doc-block img { max-width: 100%; border: 1px solid #ddd; }
    .doc-block .missing { color: #999; font-size: 9px; }

    .footer-bottom { margin-top: 10px; }
    .footer-bottom table { width: 100%; }
    .footer-bottom td { vertical-align: top; font-size: 8px; color: #888; line-height: 1.6; }
    .footer-bottom .signature-cell { text-align: center; font-size: 9px; color: #002444; }
    .footer-bottom .signature-cell .role { color: #888; font-size: 8px; display: block; margin-bottom: 2px; }
    .footer-bottom .logo-cell { text-align: {{ app()->isLocale('ar') ? 'left' : 'right' }}; }
    .footer-bottom .logo-cell img { width: 34px; }

    .footer-bar {
        margin-top: 8px;
        background-color: #006c4e;
        color: #ffffff;
        border-radius: 4px;
        padding: 7px 14px;
    }
    .footer-bar table { width: 100%; }
    .footer-bar td { font-size: 9px; vertical-align: middle; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; }

    {{-- Float, not a table cell: an inline <svg> placed inside a dompdf table
         cell was measured at zero size repeatedly (a table-layout quirk),
         while this exact float pattern was already proven to work in the
         report's title-row QR box. --}}
    .qr-strip { margin-top: 8px; overflow: hidden; }
    .qr-strip .qr-box { float: {{ app()->isLocale('ar') ? 'right' : 'left' }}; text-align: center; width: 66px; }
    .qr-strip .qr-box img, .qr-strip .qr-box svg { width: 66px; height: 66px; }
    .qr-strip .qr-box p { font-size: 7.5px; color: #777; margin: 2px 0 0; }
    .qr-strip .disclaimer {
        margin-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 78px;
        font-size: 8px; color: #888; line-height: 1.6;
    }
</style>
</head>
<body>

    <div class="page">

        {{-- Header --}}
        <div class="top-header">
            <table>
                <tr>
                    <td class="brand-cell" style="width: 26%;">
                        <table><tr>
                            <td><img class="logo-mark" src="{{ public_path('images/logo-icon.png') }}" alt=""></td>
                            <td class="brand-text">
                                <div class="brand">@ar(__('nav.app_name'))</div>
                            </td>
                        </tr></table>
                    </td>
                    <td class="title-cell" style="width: 47%;">
                        <h1>@ar(__('parcels.print_report_title'))</h1>
                        <p class="subtitle">@ar(__('parcels.print_report_subtitle'))</p>
                        <span class="report-number">@ar(__('parcels.print_report_number')): <span dir="ltr">{{ $reportNumber }}</span></span>
                    </td>
                    <td class="meta-cell">
                        <div class="meta-box">
                            @ar(__('parcels.print_generated_at'))
                            <div class="meta-value" dir="ltr">{{ now()->format('Y-m-d') }}</div>
                        </div>
                        <div class="meta-box">
                            @ar(__('parcels.print_prepared_by'))
                            <div class="meta-value">@ar(__('parcels.print_prepared_by_value'))</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Row 1: site photo+coordinates / parcel information / basic data --}}
        <div class="cards-row section">
            <table class="layout"><tr>

                <td>
                    <div class="card-title">@ar(__('parcels.site_photo_section'))</div>
                    <div class="card-body" style="padding: 0;">
                        @if ($sitePhoto)
                            <img class="card-image" src="{{ $sitePhoto }}">
                        @else
                            <p class="placeholder">@ar(__('parcels.no_site_photo'))</p>
                        @endif
                    </div>
                    @if ($centroid)
                        <div class="coords-line">
                            @ar(__('parcels.coordinates_section'))
                            <span class="value data-tabular" dir="ltr">{{ number_format($centroid['lat'], 6) }}, {{ number_format($centroid['lng'], 6) }}</span>
                        </div>
                    @endif
                </td>

                <td>
                    <div class="card-title">@ar(__('parcels.parcel_info'))</div>
                    <div class="card-body">
                        <table class="kv">
                            <x-print.kv-row :label="__('parcels.asset_type')" :value="$twin['identity']['asset_type'] ? __('parcels.asset_types.'.$twin['identity']['asset_type']) : '—'" />
                            <x-print.kv-row :label="__('parcels.area_deed')" :value="$twin['area']['deed'] ? number_format($twin['area']['deed'], 0).' '.__('dashboard.area_unit_sqm') : '—'" />
                            <x-print.kv-row :label="__('parcels.parcel_price')" :value="$twin['valuation']['total'] ? number_format($twin['valuation']['total'], 0).' '.__('parcels.sar') : '—'" />
                            <x-print.kv-row :label="__('parcels.m_price')" :value="$twin['valuation']['per_metre'] ? number_format($twin['valuation']['per_metre'], 0).' '.__('parcels.sar') : '—'" />
                            <x-print.kv-row :label="__('parcels.location')" :value="collect([$twin['identity']['district'] ?? null, $twin['identity']['city'] ?? null])->filter()->implode('، ') ?: '—'" />
                            <x-print.kv-row :label="__('parcels.plan_no')" :value="$twin['identity']['plan_no'] ?? '—'" />
                            <x-print.kv-row :label="__('parcels.geo_id')" :value="$parcel->geo_id ?? '—'" />
                            <x-print.kv-row :label="__('parcels.land_transaction')" :value="$twin['identity']['land_transaction'] ? __('parcels.land_transactions.'.$twin['identity']['land_transaction']) : '—'" />
                            <x-print.kv-row :label="__('parcels.allocation_method')" :value="$twin['identity']['allocation_method'] ?? '—'" />
                            <x-print.kv-row :label="__('parcels.fall_in')" :value="$twin['identity']['fall_in'] ?? '—'" />
                        </table>
                    </div>
                </td>

                <td>
                    <div class="card-title">@ar(__('parcels.owner_info_section'))</div>
                    <div class="card-body">
                        @php
                            $ownerNames = collect($twin['owners'])->pluck('name')->implode('، ') ?: '—';
                            $ownershipLabel = match (true) {
                                count($twin['owners']) === 1 && $twin['owners'][0]['share'] !== null => $twin['owners'][0]['share'].'%',
                                count($twin['owners']) > 1 => __('parcels.co_owned'),
                                default => '—',
                            };
                        @endphp
                        <table class="kv">
                            <x-print.kv-row :label="__('parcels.owner')" :value="$ownerNames" />
                            <x-print.kv-row :label="__('parcels.region')" :value="$twin['identity']['region'] ?? '—'" />
                            <x-print.kv-row :label="__('parcels.city')" :value="$twin['identity']['city'] ?? '—'" />
                            <x-print.kv-row :label="__('parcels.district')" :value="$twin['identity']['district'] ?? '—'" />
                            <x-print.kv-row :label="__('parcels.ownership_percentage')" :value="$ownershipLabel" />
                            <x-print.kv-row :label="__('parcels.deed_no')" :value="$parcel->currentDeed->deed_no ?? '—'" />
                            <x-print.kv-row :label="__('parcels.deed_date')" :value="$parcel->currentDeed->deed_date_hijri ?? '—'" />
                        </table>
                    </div>
                </td>

            </tr></table>
        </div>

        {{-- Row 2: boundaries & lengths / corner coordinates / proximity to services --}}
        <div class="cards-row section">
            <table class="layout"><tr>

                <td>
                    <div class="card-title">@ar(__('parcels.boundary_section'))</div>
                    <div class="card-body" style="padding: 0;">
                        @php $b = $parcel->boundary; @endphp
                        <table class="grid">
                            <thead>
                                {{-- dompdf renders <th>/<td> in strict DOM order regardless
                                     of dir="rtl" — columns are reversed here in markup so
                                     "الاتجاه" (the primary column) still lands on the
                                     right, where Arabic reading starts. --}}
                                @if (app()->isLocale('ar'))
                                    <tr>
                                        <th>@ar(__('parcels.border_length'))</th>
                                        <th>@ar(__('parcels.border_value'))</th>
                                        <th>@ar(__('parcels.border_direction'))</th>
                                    </tr>
                                @else
                                    <tr>
                                        <th>@ar(__('parcels.border_direction'))</th>
                                        <th>@ar(__('parcels.border_value'))</th>
                                        <th>@ar(__('parcels.border_length'))</th>
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @foreach ([
                                    ['dir' => __('parcels.n_direction'), 'border' => $b?->n_border, 'dim' => $b?->n_dim],
                                    ['dir' => __('parcels.s_direction'), 'border' => $b?->s_border, 'dim' => $b?->s_dim],
                                    ['dir' => __('parcels.e_direction'), 'border' => $b?->e_border, 'dim' => $b?->e_dim],
                                    ['dir' => __('parcels.w_direction'), 'border' => $b?->w_border, 'dim' => $b?->w_dim],
                                ] as $row)
                                    @if (app()->isLocale('ar'))
                                        <tr>
                                            <td dir="ltr" class="data-tabular">{{ $row['dim'] !== null ? number_format((float) $row['dim'], 0) : '—' }}</td>
                                            <td>@ar($row['border'] ?? '—')</td>
                                            <td>@ar($row['dir'])</td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td>@ar($row['dir'])</td>
                                            <td>@ar($row['border'] ?? '—')</td>
                                            <td dir="ltr" class="data-tabular">{{ $row['dim'] !== null ? number_format((float) $row['dim'], 0) : '—' }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr>
                                    <td class="total-label" colspan="2">@ar(__('parcels.area_deed'))</td>
                                    <td class="total-value" dir="ltr">{{ $twin['area']['deed'] ? number_format($twin['area']['deed'], 0).' '.__('dashboard.area_unit_sqm') : '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>

                <td>
                    <div class="card-title">@ar(__('parcels.corners_section'))</div>
                    <div class="card-body" style="padding: 0;">
                        @if (empty($corners))
                            <p class="placeholder">@ar(__('parcels.no_corners'))</p>
                        @else
                            <table class="grid">
                                <thead>
                                    @if (app()->isLocale('ar'))
                                        <tr>
                                            <th>@ar(__('parcels.northing'))</th>
                                            <th>@ar(__('parcels.easting'))</th>
                                            <th>م</th>
                                        </tr>
                                    @else
                                        <tr>
                                            <th>#</th>
                                            <th>@ar(__('parcels.easting'))</th>
                                            <th>@ar(__('parcels.northing'))</th>
                                        </tr>
                                    @endif
                                </thead>
                                <tbody>
                                    @foreach ($corners as $i => $corner)
                                        @if (app()->isLocale('ar'))
                                            <tr>
                                                <td dir="ltr" class="data-tabular">{{ number_format($corner['northing'], 3) }}</td>
                                                <td dir="ltr" class="data-tabular">{{ number_format($corner['easting'], 3) }}</td>
                                                <td>{{ $i + 1 }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td dir="ltr" class="data-tabular">{{ number_format($corner['easting'], 3) }}</td>
                                                <td dir="ltr" class="data-tabular">{{ number_format($corner['northing'], 3) }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                        <p class="geodetic-note">
                            @ar(__('parcels.geodetic_reference')): @ar(__('parcels.geodetic_utm'))<br>
                            @ar(__('parcels.geodetic_datum'))
                        </p>
                    </div>
                </td>

                <td>
                    <div class="card-title">@ar(__('parcels.proximity_section'))</div>
                    <div class="card-body">
                        <table class="kv">
                            <x-print.kv-row :label="__('parcels.proximity_mosque')" :value="__('parcels.proximity_not_available')" />
                            <x-print.kv-row :label="__('parcels.proximity_school')" :value="__('parcels.proximity_not_available')" />
                            <x-print.kv-row :label="__('parcels.proximity_hospital')" :value="__('parcels.proximity_not_available')" />
                            <x-print.kv-row :label="__('parcels.proximity_main_street')" :value="__('parcels.proximity_not_available')" />
                        </table>
                    </div>
                </td>

            </tr></table>
        </div>

        {{-- Full-width map --}}
        <div class="section">
            <div class="card">
                <div class="card-title">@ar(__('parcels.map_section'))</div>
                <div class="card-body" style="padding: 0;">
                    @if ($mapImage)
                        <img class="card-image" style="height: 230px;" src="{{ $mapImage }}">
                    @else
                        <p class="placeholder">@ar(__('parcels.map_not_rendered'))</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- QR + disclaimer --}}
        <div class="qr-strip section">
            <div class="qr-box">
                @if ($qrImage)
                    <img src="{{ $qrImage }}">
                @endif
                <p dir="ltr">{{ $twin['identity']['spatial_id'] ?? $parcel->geo_id }}</p>
            </div>
            <p class="disclaimer">@ar(__('parcels.print_footer_note'))</p>
        </div>

        {{-- Reviewer + brand mark --}}
        <div class="footer-bottom">
            <table><tr>
                <td style="width: 40%;"></td>
                <td class="signature-cell" style="width: 30%;">
                    <span class="role">@ar(__('parcels.print_reviewed_by'))</span>
                    @ar(__('parcels.print_reviewed_by_value'))
                </td>
                <td class="logo-cell" style="width: 30%;">
                    <img src="{{ public_path('images/logo-icon.png') }}" alt="">
                </td>
            </tr></table>
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
                <h2 style="background-color:#002444;color:#fff;padding:6px 10px;border-radius:3px;">@ar(__('parcels.documents_page_title'))</h2>
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
