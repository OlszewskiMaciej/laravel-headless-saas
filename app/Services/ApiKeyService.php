<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiKeyService
{
    /**
     * Create a new API key.
     *
     * @param string $name
     * @param string $service
     * @param string $environment
     * @param string|null $description
     * @param \DateTimeInterface|null $expiresAt
     * @return array<string, mixed>
     */
    public function createKey(
        string $name,
        string $service,
        string $environment,
        ?string $description = null,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        // Generate a new API key
        $plainTextKey = ApiKey::generateKey();
        
        // Hash the key for storage
        $hashedKey = ApiKey::hashKey($plainTextKey);
        
        // Create and store the API key
        $apiKey = new ApiKey([
            'name' => $name,
            'key' => $hashedKey,
            'service' => $service,
            'environment' => $environment,
            'description' => $description,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);
        
        $apiKey->save();
        
        // Return both the model and the plain text key
        return [
            'api_key' => $apiKey,
            'plain_text_key' => $plainTextKey,
        ];
    }

    /**
     * Validate an API key from the request.
     *
     * @param string $key
     * @return \App\Models\ApiKey|null
     */
    public function validateKey(string $key): ?ApiKey
    {
        // Hash the key to compare with stored values
        $hashedKey = ApiKey::hashKey($key);
        
        // Cache key lookup for performance (5 minutes)
        $cacheKey = 'api_key:' . $hashedKey;
        
        return Cache::remember($cacheKey, 300, function () use ($hashedKey) {
            $apiKey = ApiKey::where('key', $hashedKey)
                ->active()
                ->first();
            
            if ($apiKey) {
                // Update last used timestamp (but not too frequently)
                if (!$apiKey->last_used_at || now()->diffInMinutes($apiKey->last_used_at) > 60) {
                    $apiKey->last_used_at = now();
                    $apiKey->save();
                }
            }
            
            return $apiKey;
        });
    }

    /**
     * Extract API key from request.
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
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
     * Revoke an API key.
     *
     * @param \App\Models\ApiKey $apiKey
     * @return bool
     */
    public function revokeKey(ApiKey $apiKey): bool
    {
        $apiKey->is_active = false;
        $result = $apiKey->save();
        
        // Clear the cache
        $cacheKey = 'api_key:' . $apiKey->key;
        Cache::forget($cacheKey);
        
        return $result;
    }

    /**
     * Soft delete an API key.
     *
     * @param \App\Models\ApiKey $apiKey
     * @return bool|null
     */
    public function deleteKey(ApiKey $apiKey): ?bool
    {
        // Clear the cache
        $cacheKey = 'api_key:' . $apiKey->key;
        Cache::forget($cacheKey);
        
        return $apiKey->delete();
    }
}
