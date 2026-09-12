<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMapAppearanceSettingsRequest;
use App\Models\MapAppearanceSetting;
use Illuminate\Http\JsonResponse;

class MapAppearanceSettingsController extends Controller
{
    /**
     * Replaces the whole override set. The panel always sends its full,
     * currently-applied colour config (defaults included), so a partial
     * body — e.g. an empty one — genuinely means "reset the rest to default".
     */
    public function update(UpdateMapAppearanceSettingsRequest $request): JsonResponse
    {
        MapAppearanceSetting::replaceOverrides($request->validated());

        return response()->json(['colors' => MapAppearanceSetting::current()]);
    }
}
