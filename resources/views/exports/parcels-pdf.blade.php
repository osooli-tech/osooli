<!DOCTYPE html>
<html dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0b1c30; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        p.subtitle { color: #555; margin-top: 0; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: {{ app()->isLocale('ar') ? 'right' : 'left' }}; }
        th { background-color: #002444; color: #ffffff; }
        tr:nth-child(even) { background-color: #f8f9ff; }
    </style>
</head>
<body>
    <h1>{{ __('parcels.title') }}</h1>
    <p class="subtitle">{{ now()->format('Y-m-d H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('parcels.parcel_no') }}</th>
                <th>{{ __('parcels.plan_no') }}</th>
                <th>{{ __('parcels.district') }}</th>
                <th>{{ __('parcels.asset_type') }}</th>
                <th>{{ __('parcels.land_transaction') }}</th>
                <th>{{ __('parcels.deed_no') }}</th>
                <th>{{ __('parcels.deed_date') }}</th>
                <th>{{ __('parcels.area_deed') }}</th>
                <th>{{ __('parcels.deed_status') }}</th>
                <th>{{ __('parcels.deed_class') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($parcels as $parcel)
                @php $deed = $parcel->latestDeed; @endphp
                <tr>
                    <td>{{ $parcel->parcel_no ?? '—' }}</td>
                    <td>{{ $parcel->plan?->plan_no ?? '—' }}</td>
                    <td>
                        {{ app()->isLocale('ar')
                            ? ($parcel->plan?->district?->name_ar ?? '—')
                            : ($parcel->plan?->district?->name_en ?? '—') }}
                    </td>
                    <td>{{ $parcel->asset_type ? __('parcels.asset_types.'.$parcel->asset_type) : '—' }}</td>
                    <td>{{ $parcel->land_transaction ? __('parcels.land_transactions.'.$parcel->land_transaction) : '—' }}</td>
                    <td>{{ $deed?->deed_no ?? '—' }}</td>
                    <td>{{ $deed?->deed_date_hijri ?? '—' }}</td>
                    <td>{{ $deed?->deed_area ?? '—' }}</td>
                    <td>{{ $deed?->deed_status ? __('parcels.deed_statuses.'.$deed->deed_status) : '—' }}</td>
                    <td>{{ $deed?->deed_class ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
