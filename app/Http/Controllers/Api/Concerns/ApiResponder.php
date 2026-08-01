<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single source of truth for the JSON envelope the mobile app expects.
 *
 * Keeping the shape here means a controller never hand-writes `['data' => …]`,
 * so `data` / `meta` / `message` / `errors` stay identical across every
 * endpoint and can be changed in one place.
 *
 * Contract: docs/mobile-api-contract.md
 */
trait ApiResponder
{
    /** A single resource: `{ "data": … }`. */
    protected function respond(mixed $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /** A newly created resource. */
    protected function respondCreated(mixed $data): JsonResponse
    {
        return $this->respond($data, Response::HTTP_CREATED);
    }

    /** An acknowledgement carrying no resource: `{ "data": { "message": … } }`. */
    protected function respondMessage(string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return $this->respond(['message' => $message], $status);
    }

    /**
     * A paginated list: `{ "data": [...], "meta": {...} }`.
     *
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<TModel>  $paginator
     * @param  list<mixed>|null  $items  Pre-shaped items; defaults to the paginator's own.
     */
    protected function respondPaginated(LengthAwarePaginator $paginator, ?array $items = null): JsonResponse
    {
        return response()->json([
            'data' => $items ?? $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /** An unpaginated list, so the app can rely on `meta.total` everywhere. */
    protected function respondCollection(array $items): JsonResponse
    {
        return response()->json([
            'data' => $items,
            'meta' => ['total' => count($items)],
        ]);
    }

    /** A plain error: `{ "message": … }`. */
    protected function respondError(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * A validation-shaped error so the app can bind messages to fields,
     * matching Laravel's own 422 body.
     */
    protected function respondInvalid(string $field, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => [$field => [$message]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
