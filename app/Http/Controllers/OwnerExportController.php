<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\OwnersExport;
use App\Models\Owner;
use App\Services\Owner\OwnerStatisticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OwnerExportController extends Controller
{
    public function excel(Request $request): BinaryFileResponse
    {
        $export = new OwnersExport((string) $request->query('search', ''));

        return Excel::download($export, 'owners-'.now()->format('Y-m-d').'.xlsx');
    }

    /** A branded, printable portfolio report for one owner — every parcel
     *  and deed they hold, with the same weighted valuation the mobile app
     *  shows them, reused from OwnerStatisticsService rather than redone. */
    public function pdf(Owner $owner): Response
    {
        $stats = new OwnerStatisticsService($owner);

        $deeds = $owner->deeds()
            ->with('parcel.plan.district')
            ->get()
            ->sortBy(fn ($deed) => $deed->parcel?->parcel_no)
            ->values();

        $pdf = Pdf::loadView('exports.owner-print', [
            'owner' => $owner,
            'summary' => $stats->summary(),
            'portfolio' => $stats->portfolio(),
            'deeds' => $deeds,
            'reportNumber' => sprintf('SK-O-%s-%04d', now()->format('Y-m-d'), $owner->id),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("owner-{$owner->id}.pdf");
    }
}
