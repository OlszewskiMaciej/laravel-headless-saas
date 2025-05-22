<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class ApiResponse implements Responsable
{
    protected string $status;
    protected string $message;
    protected mixed $data = null;
    protected mixed $errors = null;
    protected int $statusCode;

    /**
     * Create a new API success response
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): self
    {
        $instance = new self();
        $instance->status = 'success';
        $instance->message = $message;
        $instance->data = $data;
        $instance->statusCode = $statusCode;

        return $instance;
    }

    /**
     * Create a new API error response
     */
    public static function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null): self
    {
        $instance = new self();
        $instance->status = 'error';
        $instance->message = $message;
        $instance->errors = $errors;
        $instance->statusCode = $statusCode;

        return $instance;
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): JsonResponse
    {
        $response = [
            'status' => $this->status,
            'message' => $this->message,
        ];

        if ($this->status === 'success' && $this->data !== null) {
            $response['data'] = $this->data;
        }

        if ($this->status === 'error' && $this->errors !== null) {
            $response['errors'] = $this->errors;
        }

        return response()->json($response, $this->statusCode);
    }
}
