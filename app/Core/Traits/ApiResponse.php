<?php

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a success JSON response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success(mixed $data = null, string $message = 'Operation successful', int $code = 200): JsonResponse
    {
        // Handle Laravel Resources and Collections properly
        if ($data instanceof \Illuminate\Http\Resources\Json\JsonResource ||
            $data instanceof \Illuminate\Http\Resources\Json\ResourceCollection) {
            // Resources already wrap correctly when used with response()->json()
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $data,
            ], $code);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }
    
    /**
     * Return an error JSON response.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  mixed  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error(string $message = 'Error occurred', int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
