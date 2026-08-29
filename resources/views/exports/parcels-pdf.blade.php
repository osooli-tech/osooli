<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <style>
        {{-- dompdf does not shape Arabic or apply the bidi algorithm, so every
             Arabic string below goes through @ar() to pre-shape it into
             visual order; see App\Support\PdfArabicText. --}}
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0b1c30; }
        .masthead table { width: auto; }
        .masthead .logo-mark { width: 28px; height: auto; vertical-align: middle; }
        .masthead h1 { font-size: 16px; margin: 0; padding-{{ app()->isLocale('ar') ? 'right' : 'left' }}: 8px; vertical-align: middle; }
        p.subtitle { color: #555; margin-top: 4px; margin-bottom: 16px; }
        table.data { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; }
        th { background-color: #002444; color: #ffffff; }
        tr:nth-child(even) { background-color: #f8f9ff; }
    </style>
</head>
<body>
    <div class="masthead">
        <table>
            <tr>
                <td><img class="logo-mark" src="{{ public_path('images/logo-icon.png') }}" alt=""></td>
                <td><h1>@ar(__('parcels.title'))</h1></td>
            </tr>
        </table>
    </div>
    {{-- Kept as a plain LTR span rather than run through @ar(): a bare
         timestamp is pure Latin/digits, and shaping is unnecessary here. --}}
    <p class="subtitle"><span dir="ltr">{{ now()->format('Y-m-d H:i') }}</span></p>

    <table class="data">
        <thead>
            <tr>
                <th>@ar(__('parcels.parcel_no'))</th>
                <th>@ar(__('parcels.plan_no'))</th>
                <th>@ar(__('parcels.district'))</th>
                <th>@ar(__('parcels.asset_type'))</th>
                <th>@ar(__('parcels.land_transaction'))</th>
                <th>@ar(__('parcels.deed_no'))</th>
                <th>@ar(__('parcels.deed_date'))</th>
                <th>@ar(__('parcels.area_deed'))</th>
                <th>@ar(__('parcels.deed_status'))</th>
                <th>@ar(__('parcels.deed_class'))</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($parcels as $parcel)
                @php $deed = $parcel->latestDeed; @endphp
                <tr>
                    <td>@ar($parcel->parcel_no ?? '—')</td>
                    <td>@ar($parcel->plan?->plan_no ?? '—')</td>
                    <td>
                        @ar(app()->isLocale('ar')
                            ? ($parcel->plan?->district?->name_ar ?? '—')
                            : ($parcel->plan?->district?->name_en ?? '—'))
                    </td>
                    <td>@ar($parcel->asset_type ? __('parcels.asset_types.'.$parcel->asset_type) : '—')</td>
                    <td>@ar($parcel->land_transaction ? __('parcels.land_transactions.'.$parcel->land_transaction) : '—')</td>
                    <td>@ar($deed?->deed_no ?? '—')</td>
                    <td>@ar($deed?->deed_date_hijri ?? '—')</td>
                    <td>@ar($deed?->deed_area ?? '—')</td>
                    <td>@ar($deed?->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—')</td>
                    <td>@ar($deed?->deed_class ?? '—')</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
