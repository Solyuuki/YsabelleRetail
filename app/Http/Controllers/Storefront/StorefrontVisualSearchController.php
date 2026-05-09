<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Assistant\StorefrontVisualSearchRequest;
use App\Services\Storefront\VisualProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class StorefrontVisualSearchController extends Controller
{
    public function __invoke(
        StorefrontVisualSearchRequest $request,
        VisualProductSearchService $visualSearch,
    ): JsonResponse {
        try {
            return response()->json($visualSearch->search(
                image: $request->file('image'),
                hints: $request->safe()->except('image'),
            ));
        } catch (\Throwable $exception) {
            Log::error('visual-search.unhandled', [
                'message' => $exception->getMessage(),
                'upload_filename' => $request->file('image')?->getClientOriginalName(),
            ]);

            return response()->json($visualSearch->unexpectedFailureResponse());
        }
    }
}
