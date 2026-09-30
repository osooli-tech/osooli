<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** The informational service catalogue, shown inside the portal's own layout. */
class ServiceController extends Controller
{
    private const LAYOUT = 'portal.layout';

    public function surveyRequest(): View
    {
        return view('services.survey-request', ['layout' => self::LAYOUT]);
    }

    public function engineeringDesign(): View
    {
        return view('services.engineering-design', ['layout' => self::LAYOUT]);
    }

    public function solarEnergy(): View
    {
        return view('services.solar-energy', ['layout' => self::LAYOUT]);
    }

    public function municipal(): View
    {
        return view('services.municipal', ['layout' => self::LAYOUT]);
    }

    public function valuation(): View
    {
        return view('services.coming-soon', [
            'layout' => self::LAYOUT,
            'icon' => 'assessment',
            'title' => __('services.valuation_title'),
            'description' => __('services.valuation_description'),
        ]);
    }

    public function investment(): View
    {
        return view('services.coming-soon', [
            'layout' => self::LAYOUT,
            'icon' => 'trending_up',
            'title' => __('services.investment_title'),
            'description' => __('services.investment_description'),
        ]);
    }
}
