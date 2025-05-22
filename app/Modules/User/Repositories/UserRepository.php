<?php

namespace App\Modules\User\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    /**
     * Get all users with pagination
     */
    public function getAllPaginated(int $perPage = 15, array $with = []): LengthAwarePaginator
    {
        return User::with($with)->paginate($perPage);
    }
    
    /**
     * Get all users
     */
    public function getAll(array $with = []): Collection
    {
        return User::with($with)->get();
    }
    
    /**
     * Find a user by ID
     */
    public function findById(int $id, array $with = []): ?User
    {
        return User::with($with)->find($id);
    }
    
    /**
     * Find a user by email
     */
    public function findByEmail(string $email, array $with = []): ?User
    {
        return User::with($with)->where('email', $email)->first();
    }
    
    /**
     * Create a new user
     */
    public function create(array $data): User
    {
        return User::create($data);
    }
    
    /**
     * Update a user
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }
    
    /**
     * Delete a user
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }
}
