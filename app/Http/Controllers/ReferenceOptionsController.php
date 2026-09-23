<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ReferenceOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Feeds the searchable reference pickers (<x-form.search-select>). */
class ReferenceOptionsController extends Controller
{
    public function __invoke(Request $request, string $source): JsonResponse
    {
        abort_unless(isset(ReferenceOptions::SOURCES[$source]), 404);

        $parent = $request->integer('parent');

        return response()->json(ReferenceOptions::search(
            $source,
            mb_substr((string) $request->query('q', ''), 0, 100),
            $parent > 0 ? $parent : null,
        ));
    }
}
