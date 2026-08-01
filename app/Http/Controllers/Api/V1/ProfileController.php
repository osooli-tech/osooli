<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\OwnerResource;
use App\Models\Owner;
use App\Services\Owner\OwnerStatisticsService;
use Illuminate\Http\JsonResponse;

class ProfileController extends ApiController
{
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

    /** @return array<string, mixed> */
    private function profile(Owner $owner): array
    {
        return array_merge(
            (new OwnerResource($owner))->resolve(),
            ['stats' => (new OwnerStatisticsService($owner))->profileStats()],
        );
    }
}
