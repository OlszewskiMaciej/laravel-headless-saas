<?php

namespace App\Modules\User\Repositories;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Get all users with pagination, filters, and sorting
     */
    public function getAllPaginated(int $perPage = 15, array $with = [], array $filters = [], array $sorts = []): LengthAwarePaginator
    {
        $query = QueryBuilder::for(User::class)
            ->allowedFilters($filters ?: ['name', 'email'])
            ->allowedSorts($sorts ?: ['name', 'email', 'created_at'])
            ->with($with);

        return $query->paginate($perPage);
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
    public function findById(string $id, array $with = []): ?User
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
     * Create a new user with transaction
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            return User::create($data);
        });
    }

    /**
     * Update a user with transaction
     */
    public function update(User $user, array $data): bool
    {
        return DB::transaction(function () use ($user, $data) {
            return $user->update($data);
        });
    }

    /**
     * Delete a user with transaction
     */
    public function delete(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            return $user->delete();
        });
    }

    /**
     * Sync user roles
     */
    public function syncRoles(User $user, array $roles): void
    {
        DB::transaction(function () use ($user, $roles) {
            $user->syncRoles($roles);
        });
    }
}
