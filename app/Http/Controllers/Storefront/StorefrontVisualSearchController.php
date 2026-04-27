<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Assistant\StorefrontVisualSearchRequest;
use App\Services\Storefront\VisualProductSearchService;
use Illuminate\Http\JsonResponse;

class StorefrontVisualSearchController extends Controller
{
    public function __invoke(
        StorefrontVisualSearchRequest $request,
        VisualProductSearchService $visualSearch,
    ): JsonResponse {
        return response()->json($visualSearch->search(
            image: $request->file('image'),
            hints: $request->safe()->except('image'),
        ));
    }
}
