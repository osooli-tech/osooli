<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\OwnerResource;
use App\Http\Resources\PortalAccountResource;
use App\Models\Owner;
use App\Models\PortalSetting;
use App\Services\Portal\AccountOverviewService;
use App\Services\Portal\PortalAccessService;
use App\Support\Portal\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The app's side of an account that opens several owners: the view across
 * them, and moving between them.
 */
class AccountController extends ApiController
{
    public function __construct(private readonly PortalContext $context) {}

    /**
     * Acts for another owner the account opens: a new token for that owner,
     * the one used here retired. 403 for any owner the account does not open.
     */
    public function switchOwner(Request $request, PortalAccessService $access): JsonResponse
    {
        $request->validate(['owner_id' => ['required', 'integer']]);

        $account = $this->context->account();
        $owner = Owner::query()->find($request->integer('owner_id'));

        abort_if($account === null || $owner === null || ! $account->opens((int) $owner->id), 403);

        // The token used to switch is retired: one device, one owner at a time.
        $this->owner()->currentAccessToken()->delete();

        $issued = $owner->createToken('mobile');
        $issued->accessToken->forceFill(['portal_account_id' => $account->id])->save();
        $account->forceFill(['last_owner_id' => $owner->id])->save();
        $access->audit('portal_owner_switched', $account, $owner, null);

        return $this->respond([
            'token' => $issued->plainTextToken,
            'owner' => new OwnerResource($owner),
            'account' => new PortalAccountResource($account),
            'owners' => PortalAccountResource::ownersOf($account),
        ]);
    }

    /** Every owner the account opens, each as a portfolio, and the totals. */
    public function overview(AccountOverviewService $overviews): JsonResponse
    {
        $account = $this->context->account();
        abort_if($account === null, 403);

        return $this->respond($overviews->for($account, PortalSetting::allows(PortalSetting::SHOW_VALUE)));
    }
}
