<?php

namespace App\Modules\Admin\Controllers;

use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ApiKeyService $apiKeyService)
    {
    }

    /**
     * Display a listing of API keys.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiKey::query();

        // Apply filters
        if ($request->has('service')) {
            $query->where('service', $request->input('service'));
        }

        if ($request->has('environment')) {
            $query->where('environment', $request->input('environment'));
        }

        if ($request->boolean('with_inactive', false) === false) {
            $query->where('is_active', true);
        }

        // Apply sorting
        $sortField = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Get paginated results
        $perPage = $request->input('per_page', 15);
        $apiKeys = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'API keys retrieved successfully',
            'data' => $apiKeys,
        ]);
    }

    /**
     * Store a newly created API key.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'service' => 'required|string|max:255',
            'environment' => 'required|string|max:255',
            'description' => 'nullable|string',
            'expires_days' => 'nullable|integer|min:1',
        ]);

        // Calculate expiration date if provided
        $expiresAt = null;
        if (isset($validatedData['expires_days'])) {
            $expiresAt = Carbon::now()->addDays($validatedData['expires_days']);
        }

        // Create the API key
        $result = $this->apiKeyService->createKey(
            $validatedData['name'],
            $validatedData['service'],
            $validatedData['environment'],
            $validatedData['description'] ?? null,
            $expiresAt
        );

        return response()->json([
            'status' => 'success',
            'message' => 'API key created successfully',
            'data' => [
                'api_key' => $result['api_key'],
                'plain_text_key' => $result['plain_text_key'], // Only returned once
            ],
        ], 201);
    }

    /**
     * Display the specified API key.
     */
    public function show(ApiKey $apiKey): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'API key retrieved successfully',
            'data' => $apiKey,
        ]);
    }

    /**
     * Update the specified API key.
     */
    public function update(Request $request, ApiKey $apiKey): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);

        $apiKey->fill($validatedData);
        $apiKey->save();

        // Clear cache
        $cacheKey = 'api_key:' . $apiKey->key;
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        return response()->json([
            'status' => 'success',
            'message' => 'API key updated successfully',
            'data' => $apiKey,
        ]);
    }

    /**
     * Revoke the specified API key.
     */
    public function revoke(ApiKey $apiKey): JsonResponse
    {
        $this->apiKeyService->revokeKey($apiKey);

        return response()->json([
            'status' => 'success',
            'message' => 'API key revoked successfully',
        ]);
    }

    /**
     * Remove the specified API key.
     */
    public function destroy(ApiKey $apiKey): JsonResponse
    {
        $this->apiKeyService->deleteKey($apiKey);

        return response()->json([
            'status' => 'success',
            'message' => 'API key deleted successfully',
        ], 200);
    }
}
