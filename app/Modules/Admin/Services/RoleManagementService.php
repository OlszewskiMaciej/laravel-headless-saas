<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Repositories\Interfaces\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class RoleManagementService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roleRepository
    ) {}

    /**
     * Get all roles with permissions
     */
    public function getAllRoles(): Collection
    {
        return $this->roleRepository->getAllWithPermissions();
    }

    /**
     * Create a new role
     */
    public function createRole(array $data): Role
    {
        try {
            $role = $this->roleRepository->create(['name' => $data['name']]);
            
            if (isset($data['permissions'])) {
                $this->roleRepository->syncPermissions($role, $data['permissions']);
            }

            return $role->load('permissions');
        } catch (\Exception $e) {
            Log::error('Role creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing role
     */
    public function updateRole(Role $role, array $data): Role
    {
        try {
            if (isset($data['name'])) {
                $this->roleRepository->update($role, ['name' => $data['name']]);
            }
            
            if (isset($data['permissions'])) {
                $this->roleRepository->syncPermissions($role, $data['permissions']);
            }

            return $role->fresh(['permissions']);
        } catch (\Exception $e) {
            Log::error('Role update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a role
     */
    public function deleteRole(Role $role): bool
    {
        try {
            return $this->roleRepository->delete($role);
        } catch (\Exception $e) {
            Log::error('Role deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get role by ID
     */
    public function getRoleById(string $id): ?Role
    {
        return $this->roleRepository->findById($id, ['permissions']);
    }
}
