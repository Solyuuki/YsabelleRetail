<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\Assistant\StorefrontAssistantMessageRequest;
use App\Services\Storefront\SmartShoppingAssistantService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorefrontAssistantController extends Controller
{
    public function message(
        StorefrontAssistantMessageRequest $request,
        SmartShoppingAssistantService $assistant,
    ): JsonResponse {
        return response()->json($assistant->respond($request->string('message')->toString()));
    }

    public function stream(
        StorefrontAssistantMessageRequest $request,
        SmartShoppingAssistantService $assistant,
    ): StreamedResponse {
        $message = $request->string('message')->toString();

        return response()->stream(function () use ($assistant, $message): void {
            try {
                foreach ($assistant->stream($message) as $event) {
                    echo 'event: '.$event['event']."\n";
                    echo 'data: '.json_encode($event['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }

                    flush();
                }
            } catch (\Throwable $exception) {
                echo "event: error\n";
                echo 'data: '.json_encode([
                    'message' => 'The assistant is temporarily unavailable. Please try again.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }

                flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
