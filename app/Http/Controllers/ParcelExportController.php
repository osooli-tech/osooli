<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ParcelsExport;
use App\Models\Parcel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ParcelExportController extends Controller
{
    public function excel(Request $request): BinaryFileResponse
    {
        $export = new ParcelsExport(
            (string) $request->query('search', ''),
            (string) $request->query('filterAssetType', ''),
            (string) $request->query('filterLandTransaction', ''),
            (string) $request->query('filterDeedStatus', ''),
        );

        return Excel::download($export, 'parcels-'.now()->format('Y-m-d').'.xlsx');
    }

    public function pdf(Request $request): Response
    {
        $parcels = Parcel::query()
            ->with(['plan.district', 'latestDeed'])
            ->filtered(
                (string) $request->query('search', ''),
                (string) $request->query('filterAssetType', ''),
                (string) $request->query('filterLandTransaction', ''),
                (string) $request->query('filterDeedStatus', ''),
            )
            ->orderBy('parcel_no')
            ->get();

        $pdf = Pdf::loadView('exports.parcels-pdf', ['parcels' => $parcels])
            ->setPaper('a4', 'landscape');

        return $pdf->download('parcels-'.now()->format('Y-m-d').'.pdf');
    }
}
