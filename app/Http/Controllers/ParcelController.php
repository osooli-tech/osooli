<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Parcel;
use Illuminate\Contracts\View\View;

class ParcelController extends Controller
{
    public function show(Parcel $parcel): View
    {
        $parcel->load([
            'plan.district',
            'deeds.owners',
            'boundary.engineeringOffice',
            'surveyDecisions',
            'photos',
        ]);

        return view('parcels.show', compact('parcel'));
    }
}
