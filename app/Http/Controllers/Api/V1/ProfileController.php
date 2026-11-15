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

class ProfileController extends ApiController
{
    public function __construct(private readonly PortalContext $context) {}

    public function show(): JsonResponse
    {
        return $this->respond($this->profile($this->owner()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $owner = $this->owner();
        $owner->update($request->validated());

        return $this->respond($this->profile($owner->refresh()));
    }

    /**
     * The owner, and beside it the account that signed in with the owners it
     * opens — so the app refreshes what it may do each time it starts.
     *
     * @return array<string, mixed>
     */
    private function profile(Owner $owner): array
    {
        $account = $this->context->account();

        return array_merge(
            (new OwnerResource($owner))->resolve(),
            ['stats' => (new OwnerStatisticsService($owner))->profileStats()],
            $account === null ? [] : [
                'account' => (new PortalAccountResource($account))->resolve(),
                'owners' => PortalAccountResource::ownersOf($account),
            ],
        );
    }
}
