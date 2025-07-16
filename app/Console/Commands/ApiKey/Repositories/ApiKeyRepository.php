<?php

namespace App\Console\Commands\ApiKey\Repositories;

use App\Console\Commands\ApiKey\Repositories\Interfaces\ApiKeyRepositoryInterface;
use App\Models\ApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ApiKeyRepository implements ApiKeyRepositoryInterface
{
    /**
     * Get all API keys with pagination and filters
     */
    public function getAllPaginated(int $perPage = 15, array $filters = [], array $sorts = []): LengthAwarePaginator
    {
        $query = ApiKey::query();

        // Apply filters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                if ($field === 'with_inactive' && $value === false) {
                    $query->where('is_active', true);
                } else {
                    $query->where($field, $value);
                }
            }
        }

        // Apply sorting
        if (!empty($sorts)) {
            foreach ($sorts as $field => $direction) {
                $query->orderBy($field, $direction);
            }
        } else {
            $query->latest();
        }

        return $query->paginate($perPage);
    }
    
    /**
     * Get all API keys
     */
    public function getAll(): Collection
    {
        return ApiKey::all();
    }
    
    /**
     * Find API key by ID
     */
    public function findById(string $id): ?ApiKey
    {
        return ApiKey::find($id);
    }
    
    /**
     * Find API key by hashed key
     */
    public function findByKey(string $hashedKey): ?ApiKey
    {
        return ApiKey::where('key', $hashedKey)->first();
    }
    
    /**
     * Create a new API key with transaction
     */
    public function create(array $data): ApiKey
    {
        return DB::transaction(function () use ($data) {
            return ApiKey::create($data);
        });
    }
    
    /**
     * Update an API key with transaction
     */
    public function update(ApiKey $apiKey, array $data): bool
    {
        return DB::transaction(function () use ($apiKey, $data) {
            return $apiKey->update($data);
        });
    }
    
    /**
     * Delete an API key with transaction
     */
    public function delete(ApiKey $apiKey): bool
    {
        return DB::transaction(function () use ($apiKey) {
            return $apiKey->delete();
        });
    }
    
    /**
     * Deactivate an API key
     */
    public function deactivate(ApiKey $apiKey): bool
    {
        return $this->update($apiKey, ['is_active' => false]);
    }
}
