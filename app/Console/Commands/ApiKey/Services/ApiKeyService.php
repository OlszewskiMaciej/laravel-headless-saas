<?php

namespace App\Console\Commands\ApiKey\Services;

use App\Console\Commands\ApiKey\Repositories\Interfaces\ApiKeyRepositoryInterface;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiKeyService
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeyRepository
    ) {}

    /**
     * Create a new API key.
     */
    public function createKey(
        string $name,
        string $service,
        string $environment,
        ?string $description = null,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        try {
            // Generate a new API key
            $plainTextKey = ApiKey::generateKey();
            
            // Hash the key for storage
            $hashedKey = ApiKey::hashKey($plainTextKey);
            
            // Create and store the API key
            $apiKey = $this->apiKeyRepository->create([
                'name' => $name,
                'key' => $hashedKey,
                'service' => $service,
                'environment' => $environment,
                'description' => $description,
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]);
            
            // Return both the model and the plain text key
            return [
                'api_key' => $apiKey,
                'plain_text_key' => $plainTextKey,
            ];
        } catch (\Exception $e) {
            Log::error('API key creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate an API key from the request.
     */
    public function validateKey(string $key): ?ApiKey
    {
        try {
            // Hash the key to compare with stored values
            $hashedKey = ApiKey::hashKey($key);
            
            // Cache key lookup for performance (5 minutes)
            $cacheKey = 'api_key:' . $hashedKey;
            
            return Cache::remember($cacheKey, 300, function () use ($hashedKey) {
                $apiKey = $this->apiKeyRepository->findByKey($hashedKey);
                
                if ($apiKey && $apiKey->is_active && (!$apiKey->expires_at || $apiKey->expires_at->isFuture())) {
                    // Update last used timestamp (but not too frequently)
                    if (!$apiKey->last_used_at || now()->diffInMinutes($apiKey->last_used_at) > 60) {
                        $this->apiKeyRepository->update($apiKey, ['last_used_at' => now()]);
                    }
                    return $apiKey;
                }
                
                return null;
            });
        } catch (\Exception $e) {
            Log::error('API key validation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract API key from request.
     */
    public function extractKeyFromRequest(Request $request): ?string
    {
        // Check header (recommended approach)
        $key = $request->header('X-API-KEY');
        
        // Fallback to query parameter (not recommended for production)
        if (!$key) {
            $key = $request->query('api_key');
        }
        
        return $key;
    }

    /**
     * Get paginated API keys with filters.
     */
    public function getApiKeys(array $filters = [], array $sortOptions = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        try {
            return $this->apiKeyRepository->getAllPaginated($perPage, $filters, $sortOptions);
        } catch (\Exception $e) {
            Log::error('Failed to get API keys: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an API key.
     */
    public function updateKey(ApiKey $apiKey, array $data): ApiKey
    {
        try {
            $result = $this->apiKeyRepository->update($apiKey, $data);
            
            // Clear the cache
            $cacheKey = 'api_key:' . $apiKey->key;
            Cache::forget($cacheKey);
            
            // Return the updated ApiKey object
            return $apiKey->fresh();
        } catch (\Exception $e) {
            Log::error('API key update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Revoke an API key.
     */
    public function revokeKey(ApiKey $apiKey): bool
    {
        try {
            $result = $this->apiKeyRepository->deactivate($apiKey);
            
            // Clear the cache
            $cacheKey = 'api_key:' . $apiKey->key;
            Cache::forget($cacheKey);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('API key revocation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Soft delete an API key.
     */
    public function deleteKey(ApiKey $apiKey): bool
    {
        try {
            // Clear the cache
            $cacheKey = 'api_key:' . $apiKey->key;
            Cache::forget($cacheKey);
            
            return $this->apiKeyRepository->delete($apiKey);
        } catch (\Exception $e) {
            Log::error('API key deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
