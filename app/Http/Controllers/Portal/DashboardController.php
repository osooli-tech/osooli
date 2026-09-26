<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Services\Owner\OwnerStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(): View
    {
        /** @var Owner $owner */
        $owner = Auth::guard('owner')->user();

        $stats = new OwnerStatisticsService($owner);

        return view('portal.dashboard', [
            'greetingName' => Str::before(trim($owner->name), ' '),
            'summary' => $stats->summary(),
        ]);
    }
}
