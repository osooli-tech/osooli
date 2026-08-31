<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * The service catalogue pages under "الخدمات" in the sidebar. Every page here
 * is informational — none of them reads from the platform's own data — so
 * each view carries its own content rather than being driven by a database
 * table that would add nothing over a config array.
 */
class ServiceController extends Controller
{
    public function surveyRequest(): View
    {
        return view('services.survey-request');
    }

    public function engineeringDesign(): View
    {
        return view('services.engineering-design');
    }

    public function solarEnergy(): View
    {
        return view('services.solar-energy');
    }

    public function municipal(): View
    {
        return view('services.municipal');
    }

    public function valuation(): View
    {
        return view('services.coming-soon', [
            'icon' => 'assessment',
            'title' => __('services.valuation_title'),
            'description' => __('services.valuation_description'),
        ]);
    }

    public function investment(): View
    {
        return view('services.coming-soon', [
            'icon' => 'trending_up',
            'title' => __('services.investment_title'),
            'description' => __('services.investment_description'),
        ]);
    }
}
