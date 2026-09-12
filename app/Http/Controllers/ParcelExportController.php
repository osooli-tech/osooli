<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ParcelsExport;
use App\Models\Parcel;
use App\Models\User;
use App\Support\OwnerScope;
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
            $this->visibleParcelIds($request),
        );

        return Excel::download($export, 'parcels-'.now()->format('Y-m-d').'.xlsx');
    }

    public function pdf(Request $request): Response
    {
        $parcelIds = $this->visibleParcelIds($request);

        $parcels = Parcel::query()
            ->with(['plan.district', 'latestDeed'])
            ->when($parcelIds !== null, fn ($query) => $query->whereIn('parcels.id', $parcelIds))
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

    /**
     * An export holds exactly what the parcel list shows, so a user restricted
     * to specific owners gets only their own parcels here too.
     *
     * @return list<int>|null null means unrestricted
     */
    private function visibleParcelIds(Request $request): ?array
    {
        /** @var User|null $user */
        $user = $request->user();

        return OwnerScope::parcelIds($user);
    }
}
