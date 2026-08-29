<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePresentationRequest;
use App\Models\PresentationRequest;
use Illuminate\Http\JsonResponse;

class PresentationRequestController extends Controller
{
    public function store(StorePresentationRequest $request): JsonResponse
    {
        PresentationRequest::create($request->validated());

        return response()->json([
            'message' => __('landing.request_form_success'),
        ], 201);
    }
}
