<?php

namespace App\Http\Controllers\Admin\Realtime;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminActivityFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityFeedController extends Controller
{
    public function __invoke(Request $request, AdminActivityFeedService $feed): JsonResponse
    {
        $after = $request->query('after');

        return response()->json(
            $feed->snapshot(is_numeric($after) ? (int) $after : null)
        );
    }
}
