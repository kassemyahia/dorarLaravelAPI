<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BaseApiController extends Controller
{
    protected function sendSuccess(int $statusCode, mixed $data, array $metadata = []): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'metadata' => $metadata,
            'data' => $data,
        ], $statusCode);
    }

    protected function sendError(string $message, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }
}
