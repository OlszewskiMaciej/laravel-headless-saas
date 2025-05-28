<?php

namespace App\Modules\Admin\Controllers;

use App\Models\ApiKey;
use App\Modules\Admin\Requests\CreateApiKeyRequest;
use App\Modules\Admin\Requests\UpdateApiKeyRequest;
use App\Core\Traits\ApiResponse;
use App\Services\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ApiKeyController extends Controller
{
    use ApiResponse;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly ApiKeyService $apiKeyService
    ) {}    /**
     * Display a listing of API keys.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [];
            
            // Apply filters
            if ($request->has('service')) {
                $filters['service'] = $request->input('service');
            }

            if ($request->has('environment')) {
                $filters['environment'] = $request->input('environment');
            }

            if ($request->boolean('with_inactive', false) === false) {
                $filters['is_active'] = true;
            }            // Apply sorting
            $sortOptions = [
                $request->input('sort_by', 'created_at') => $request->input('sort_direction', 'desc'),
            ];

            // Get paginated results
            $perPage = $request->input('per_page', 15);
            $apiKeys = $this->apiKeyService->getApiKeys($filters, $sortOptions, $perPage);

            return $this->success($apiKeys, 'API keys retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve API keys: ' . $e->getMessage(), [
                'filters' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to retrieve API keys', 500);
        }
    }    /**
     * Store a newly created API key.
     */
    public function store(CreateApiKeyRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Calculate expiration date if provided
            $expiresAt = null;
            if (isset($validated['expires_days'])) {
                $expiresAt = Carbon::now()->addDays($validated['expires_days']);
            }

            // Create the API key
            $result = $this->apiKeyService->createKey(
                $validated['name'],
                $validated['service'],
                $validated['environment'],
                $validated['description'] ?? null,
                $expiresAt
            );

            return $this->success([
                'api_key' => $result['api_key'],
                'plain_text_key' => $result['plain_text_key'], // Only returned once
            ], 'API key created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Failed to create API key: ' . $e->getMessage(), [
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to create API key', 500);
        }
    }    /**
     * Display the specified API key.
     */
    public function show(ApiKey $apiKey): JsonResponse
    {
        try {
            return $this->success($apiKey, 'API key retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve API key: ' . $e->getMessage(), [
                'api_key_id' => $apiKey->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to retrieve API key', 500);
        }
    }

    /**
     * Update the specified API key.
     */
    public function update(UpdateApiKeyRequest $request, ApiKey $apiKey): JsonResponse
    {
        try {
            $validated = $request->validated();
            $updatedApiKey = $this->apiKeyService->updateKey($apiKey, $validated);

            return $this->success($updatedApiKey, 'API key updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update API key: ' . $e->getMessage(), [
                'api_key_id' => $apiKey->id,
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to update API key', 500);
        }
    }    /**
     * Revoke the specified API key.
     */
    public function revoke(ApiKey $apiKey): JsonResponse
    {
        try {
            $this->apiKeyService->revokeKey($apiKey);
            
            return $this->success(null, 'API key revoked successfully');
        } catch (\Exception $e) {
            Log::error('Failed to revoke API key: ' . $e->getMessage(), [
                'api_key_id' => $apiKey->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to revoke API key', 500);
        }
    }

    /**
     * Remove the specified API key.
     */
    public function destroy(ApiKey $apiKey): JsonResponse
    {
        try {
            $this->apiKeyService->deleteKey($apiKey);
            
            return $this->success(null, 'API key deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete API key: ' . $e->getMessage(), [
                'api_key_id' => $apiKey->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to delete API key', 500);
        }
    }
}
