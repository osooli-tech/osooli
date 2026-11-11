<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\PortalSetting;
use App\Services\Portal\AccountOverviewService;
use App\Support\Portal\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What belongs to the signed-in account rather than to one owner: the view
 * across all the owners it opens, and moving between them.
 */
class AccountController extends Controller
{
    public function __construct(private readonly PortalContext $context) {}

    /** Every owner the account opens, each as a portfolio; one owner has nothing to overview. */
    public function overview(AccountOverviewService $overviews): View|RedirectResponse
    {
        $account = $this->context->account();

        if ($account === null || ! $this->context->hasMany()) {
            return redirect()->route('portal.dashboard');
        }

        $showValue = PortalSetting::allows(PortalSetting::SHOW_VALUE);

        return view('portal.overview', [
            'account' => $account,
            'overview' => $overviews->for($account, $showValue),
            'explorer' => $overviews->explorer($account, $showValue),
            'showValue' => $showValue,
        ]);
    }

    public function overviewMap(AccountOverviewService $overviews): JsonResponse
    {
        $account = $this->context->account();

        return response()->json($account === null ? ['type' => 'FeatureCollection', 'features' => []] : $overviews->map($account));
    }

    /**
     * Act for another owner the account opens; 403 for any other. Given one
     * of that owner's parcels, go straight to it.
     */
    public function switch(Request $request, Owner $owner): RedirectResponse
    {
        $this->context->switchTo($owner);

        $parcelId = $request->integer('parcel');

        if ($parcelId > 0 && $owner->parcels()->whereKey($parcelId)->exists()) {
            return redirect()->route('portal.parcels.show', $parcelId);
        }

        return redirect()->route('portal.dashboard');
    }
}
