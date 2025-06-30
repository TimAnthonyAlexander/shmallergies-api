<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    /**
     * Return a ping response
     *
     * @return JsonResponse
     */
    public function ping(): JsonResponse
    {
        return response()->json(['message' => 'Ping!']);
    }
} 