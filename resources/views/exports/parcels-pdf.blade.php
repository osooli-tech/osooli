<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <style>
        {{-- dompdf does not shape Arabic or apply the bidi algorithm, so every
             Arabic string below goes through @ar() to pre-shape it into
             visual order; see App\Support\PdfArabicText. It also renders
             <th>/<td> in strict DOM order regardless of dir="rtl" — it does
             not reverse table columns the way a browser does — so the column
             order itself is reversed in markup for Arabic, below. --}}
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

    @php
        $columns = [
            ['key' => 'parcel_no', 'label' => __('parcels.parcel_no')],
            ['key' => 'plan_no', 'label' => __('parcels.plan_no')],
            ['key' => 'district', 'label' => __('parcels.district')],
            ['key' => 'asset_type', 'label' => __('parcels.asset_type')],
            ['key' => 'land_transaction', 'label' => __('parcels.land_transaction')],
            ['key' => 'deed_no', 'label' => __('parcels.deed_no')],
            ['key' => 'deed_date', 'label' => __('parcels.deed_date')],
            ['key' => 'area_deed', 'label' => __('parcels.area_deed')],
            ['key' => 'deed_status', 'label' => __('parcels.deed_status')],
            ['key' => 'deed_class', 'label' => __('parcels.deed_class')],
        ];

        if (app()->isLocale('ar')) {
            $columns = array_reverse($columns);
        }
    @endphp

    <table class="data">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>@ar($column['label'])</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($parcels as $parcel)
                @php
                    $deed = $parcel->latestDeed;

                    $cells = [
                        'parcel_no' => $parcel->parcel_no ?? '—',
                        'plan_no' => $parcel->plan?->plan_no ?? '—',
                        'district' => app()->isLocale('ar')
                            ? ($parcel->plan?->district?->name_ar ?? '—')
                            : ($parcel->plan?->district?->name_en ?? '—'),
                        'asset_type' => $parcel->asset_type ? __('parcels.asset_types.'.$parcel->asset_type) : '—',
                        'land_transaction' => $parcel->land_transaction ? __('parcels.land_transactions.'.$parcel->land_transaction) : '—',
                        'deed_no' => $deed?->deed_no ?? '—',
                        'deed_date' => $deed?->deed_date_hijri ?? '—',
                        'area_deed' => $deed?->deed_area ?? '—',
                        'deed_status' => $deed?->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—',
                        'deed_class' => $deed?->deed_class ?? '—',
                    ];
                @endphp
                <tr>
                    @foreach ($columns as $column)
                        <td>@ar((string) $cells[$column['key']])</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
