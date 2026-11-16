<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\OwnerResource;
use App\Http\Resources\PortalAccountResource;
use App\Models\Owner;
use App\Services\Owner\OwnerStatisticsService;
use App\Support\Portal\PortalContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends ApiController
{
    public function __construct(private readonly PortalContext $context) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($this->profile($request, $this->owner()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $owner = $this->owner();
        $owner->update($request->validated());

        return $this->respond($this->profile($request, $owner->refresh()));
    }

    /**
     * The owner, and beside it the account that signed in with the owners it
     * opens — so the app refreshes what it may do each time it starts.
     *
     * The counters follow the read scope like the dashboard's, so an account
     * reading all its owners counts all their land, not the token's owner's.
     *
     * @return array<string, mixed>
     */
    private function profile(Request $request, Owner $owner): array
    {
        $account = $this->context->account();
        $details = (new OwnerResource($owner))->resolve();

        return array_merge(
            $details,
            ['stats' => (new OwnerStatisticsService($this->readingOwner($request)))->profileStats()],
            $account === null ? [] : [
                'account' => (new PortalAccountResource($account))->resolve(),
                'owners' => PortalAccountResource::ownersOf($account),
            ],
        );
    }
}
