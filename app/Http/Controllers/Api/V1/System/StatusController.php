<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'application' => config('app.name'),
            'status' => 'ok',
            'version' => 1,
        ]);
    }
}
