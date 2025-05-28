<?php

namespace App\Modules\Admin\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    public function getAllWithPermissions(): Collection;
    
    public function findById(string $id, array $with = []): ?Role;
    
    public function findByName(string $name): ?Role;
    
    public function create(array $data): Role;
    
    public function update(Role $role, array $data): bool;
    
    public function delete(Role $role): bool;
    
    public function getAllPaginated(int $perPage = 15, array $filters = [], array $with = [], array $sorts = []): LengthAwarePaginator;
    
    public function syncPermissions(Role $role, array $permissions): void;
}
