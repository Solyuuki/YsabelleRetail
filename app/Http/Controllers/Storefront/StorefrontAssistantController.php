<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Assistant\StorefrontAssistantMessageRequest;
use App\Services\Storefront\SmartShoppingAssistantService;
use Illuminate\Http\JsonResponse;

class StorefrontAssistantController extends Controller
{
    public function __invoke(
        StorefrontAssistantMessageRequest $request,
        SmartShoppingAssistantService $assistant,
    ): JsonResponse {
        return response()->json($assistant->respond($request->string('message')->toString()));
    }
}
