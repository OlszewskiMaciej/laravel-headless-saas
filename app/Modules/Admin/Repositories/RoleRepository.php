<?php

namespace App\Modules\Admin\Repositories;

use App\Modules\Admin\Repositories\Interfaces\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\QueryBuilder;

class RoleRepository implements RoleRepositoryInterface
{
    /**
     * Get all roles with their permissions
     */
    public function getAllWithPermissions(): Collection
    {
        return Role::with('permissions')->get();
    }
    
    /**
     * Find role by ID
     */
    public function findById(string $id, array $with = []): ?Role
    {
        return Role::with($with)->find($id);
    }
    
    /**
     * Find role by name
     */
    public function findByName(string $name): ?Role
    {
        return Role::where('name', $name)->first();
    }
    
    /**
     * Create a new role with transaction
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            return Role::create(array_merge($data, ['guard_name' => 'web']));
        });
    }
    
    /**
     * Update a role with transaction
     */
    public function update(Role $role, array $data): bool
    {
        return DB::transaction(function () use ($role, $data) {
            return $role->update($data);
        });
    }
    
    /**
     * Delete a role with transaction
     */
    public function delete(Role $role): bool
    {
        return DB::transaction(function () use ($role) {
            return $role->delete();
        });
    }
    
    /**
     * Get all roles with pagination, filters, and sorting
     */
    public function getAllPaginated(int $perPage = 15, array $filters = [], array $with = [], array $sorts = []): LengthAwarePaginator
    {
        $query = QueryBuilder::for(Role::class)
            ->allowedFilters($filters ?: ['name'])
            ->allowedSorts($sorts ?: ['name', 'created_at'])
            ->with($with ?: ['permissions']);

        return $query->paginate($perPage);
    }
    
    /**
     * Sync permissions for a role
     */
    public function syncPermissions(Role $role, array $permissions): void
    {
        DB::transaction(function () use ($role, $permissions) {
            $role->syncPermissions($permissions);
        });
    }
}
