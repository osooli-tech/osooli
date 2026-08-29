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
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #0b1c30; margin: 0; }

    .header {
        background-color: #002444;
        color: #ffffff;
        padding: 14px 24px;
    }
    .header table { width: auto; }
    .header .logo-mark { width: 40px; height: auto; vertical-align: middle; }
    .header .brand-text { vertical-align: middle; padding-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 10px; }
    .header .brand { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; }
    .header .tagline { font-size: 10px; color: #abc9f2; margin-top: 2px; }

    .body { padding: 20px 24px; }

    .title-row { overflow: hidden; margin-bottom: 14px; }
    .title-row h1 { font-size: 20px; margin: 0 0 4px; }
    .title-row .subtitle { font-size: 11px; color: #555; margin: 0; }
    .qr-box { float: {{ app()->isLocale('ar') ? 'left' : 'right' }}; text-align: center; }
    .qr-box img, .qr-box svg { width: 90px; height: 90px; }
    .qr-box p { font-size: 8px; color: #777; margin: 4px 0 0; width: 90px; }

    .section { margin-bottom: 14px; }
    .section h2 {
        font-size: 12px;
        color: #002444;
        background-color: #f2f0ea;
        padding: 5px 8px;
        margin: 0 0 6px;
        border-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 3px solid #006c4e;
    }

    table { width: 100%; border-collapse: collapse; }
    table.kv td { padding: 3px 8px; vertical-align: top; border-bottom: 1px solid #eee; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; }
    table.kv td.label { color: #666; width: 33%; font-size: 10.5px; }
    table.kv td.value { font-weight: bold; }

    table.list { border: 1px solid #ddd; }
    table.list th, table.list td { border: 1px solid #ddd; padding: 5px 7px; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; }
    table.list th { background-color: #002444; color: #fff; font-size: 10px; }
    table.list tr:nth-child(even) { background-color: #f8f9ff; }

    .footer {
        margin-top: 18px;
        padding-top: 8px;
        border-top: 1px solid #ddd;
        font-size: 9px;
        color: #888;
        text-align: center;
    }
</style>
</head>
<body>

    <div class="header">
        {{-- A table, not flex/inline-block, because dompdf's support for those
             is unreliable for vertical alignment. --}}
        <table>
            <tr>
                <td><img class="logo-mark" src="{{ public_path('images/logo-icon.png') }}" alt=""></td>
                <td class="brand-text">
                    <div class="brand">@ar(__('nav.app_name'))</div>
                    <div class="tagline">@ar(__('parcels.print_report_title'))</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">

        <div class="title-row">
            <div class="qr-box">
                {!! $qrSvg !!}
                <p>{{ $twin['identity']['spatial_id'] ?? $parcel->geo_id }}</p>
            </div>
            <h1>@ar(__('parcels.parcel_no').' '.$parcel->parcel_no)</h1>
            {{-- The label and the timestamp are shaped separately rather than
                 as one concatenated string: mixing an Arabic label with a
                 "HH:MM"-shaped numeric run confuses the shaper's bidi
                 handling and swaps the hour/minute groups. --}}
            <p class="subtitle">@ar(__('parcels.print_generated_at')): <span dir="ltr">{{ now()->format('Y-m-d H:i') }}</span></p>
        </div>

        {{-- Identity --}}
        <div class="section">
            <h2>@ar(__('parcels.parcel_info'))</h2>
            <table class="kv">
                <tr>
                    <td class="label">@ar(__('parcels.spatial_id'))</td>
                    <td class="value">@ar($twin['identity']['spatial_id'] ?? '—')</td>
                    <td class="label">@ar(__('parcels.plan_no'))</td>
                    <td class="value">@ar($twin['identity']['plan_no'] ?? '—')</td>
                </tr>
                <tr>
                    <td class="label">@ar(__('parcels.district'))</td>
                    <td class="value">@ar($twin['identity']['district'] ?? '—')</td>
                    <td class="label">@ar(__('parcels.city'))</td>
                    <td class="value">@ar($twin['identity']['city'] ?? '—')</td>
                </tr>
                <tr>
                    <td class="label">@ar(__('parcels.asset_type'))</td>
                    <td class="value">@ar($twin['identity']['asset_type'] ? __('parcels.asset_types.'.$twin['identity']['asset_type']) : '—')</td>
                    <td class="label">@ar(__('parcels.area_deed_label'))</td>
                    <td class="value">
                        @ar($twin['area']['deed'] ? number_format($twin['area']['deed'], 0).' '.__('dashboard.area_unit_sqm') : '—')
                    </td>
                </tr>
            </table>
        </div>

        {{-- Deeds --}}
        <div class="section">
            <h2>@ar(__('parcels.deeds_section'))</h2>
            @if ($parcel->deeds->isEmpty())
                <p style="color:#888;">@ar(__('parcels.no_deeds'))</p>
            @else
                <table class="list">
                    <thead>
                        <tr>
                            <th>@ar(__('parcels.deed_no'))</th>
                            <th>@ar(__('parcels.deed_date'))</th>
                            <th>@ar(__('parcels.deed_status'))</th>
                            <th>@ar(__('parcels.deed_class'))</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parcel->deeds as $deed)
                            <tr>
                                <td>@ar($deed->deed_no ?? '—')</td>
                                <td>@ar($deed->deed_date_hijri ?? '—')</td>
                                <td>@ar($deed->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—')</td>
                                <td>@ar($deed->deed_class ?? '—')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Owners --}}
        <div class="section">
            <h2>@ar(__('parcels.owners_section'))</h2>
            @if (empty($twin['owners']))
                <p style="color:#888;">@ar(__('parcels.not_recorded'))</p>
            @else
                <table class="list">
                    <thead>
                        <tr>
                            <th>@ar(__('owners.name'))</th>
                            <th>@ar(__('owners.national_id'))</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($twin['owners'] as $owner)
                            <tr>
                                <td>@ar($owner['name'])</td>
                                <td>@ar($owner['national_id'] ?? '—')</td>
                            </tr>
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
                <table class="kv">
                    <tr>
                        <td class="label">@ar(__('parcels.n_border'))</td>
                        <td class="value">@ar($b->n_border ?? '—')</td>
                        <td class="label">@ar(__('parcels.s_border'))</td>
                        <td class="value">@ar($b->s_border ?? '—')</td>
                    </tr>
                    <tr>
                        <td class="label">@ar(__('parcels.e_border'))</td>
                        <td class="value">@ar($b->e_border ?? '—')</td>
                        <td class="label">@ar(__('parcels.w_border'))</td>
                        <td class="value">@ar($b->w_border ?? '—')</td>
                    </tr>
                </table>
            </div>
        @endif

    </div>

    {{-- Map: a static image rather than the live Mapbox map, which cannot run
         inside dompdf. Rasterised server-side (see ParcelMapSvgService) rather
         than embedded as raw <svg>: dompdf's own inline-SVG support silently
         drops shapes and text, rendering only the QR code's single-path SVG
         correctly. One page of its own, since it needs the full width. --}}
    @if ($mapImage)
        <div class="body" style="page-break-before: always;">
            <div class="section">
                <h2>@ar(__('parcels.map_section'))</h2>
                <div style="text-align: center;">
                    <img src="{{ $mapImage }}" style="max-width: 100%;">
                </div>
            </div>
        </div>
    @endif

    {{-- Documents: each stored file is a PDF, so its first page is rasterised
         server-side (see ParcelDocumentRenderService) rather than embedded as
         a PDF-within-a-PDF, which dompdf cannot do. --}}
    @if ($documents->isNotEmpty())
        <div class="body" style="page-break-before: always;">
            <div class="section">
                <h2>@ar(__('parcels.documents_page_title'))</h2>
                @foreach ($documents as $doc)
                    <div style="margin-bottom: 18px; {{ ! $loop->first ? 'page-break-before: always;' : '' }}">
                        <p style="font-size: 11px; font-weight: bold; color: #002444; margin-bottom: 6px;">
                            @ar($doc['photo']->photo_type?->value ?? '—')
                        </p>
                        @if ($doc['preview'])
                            <img src="{{ $doc['preview'] }}" style="max-width: 100%; border: 1px solid #ddd;">
                        @else
                            <p style="color: #888; font-size: 10px;">@ar(__('parcels.document_not_rendered'))</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="footer">
        @ar(__('parcels.print_footer_note'))
    </div>

</body>
</html>
