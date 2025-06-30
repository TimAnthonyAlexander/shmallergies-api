<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    /**
     * Return a ping response.
     */
    public function ping(): JsonResponse
    {
        return response()->json(['message' => 'Ping!']);
    }
}
