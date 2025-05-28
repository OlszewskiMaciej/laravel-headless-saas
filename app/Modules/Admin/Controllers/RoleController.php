<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Admin\Resources\RoleCollection;
use App\Modules\Admin\Resources\RoleResource;
use App\Modules\Admin\Services\RoleManagementService;
use App\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class RoleController
{
    use ApiResponse;

    public function __construct(
        private readonly RoleManagementService $roleManagementService
    ) {}

    /**
     * List all roles
     */
    public function index(): JsonResponse
    {
        try {
            $roles = $this->roleManagementService->getAllRoles();
            
            return $this->success(new RoleCollection($roles));
        } catch (\Exception $e) {
            Log::error('Failed to fetch roles: ' . $e->getMessage());
            return $this->error('Failed to fetch roles', 500);
        }
    }
    
    /**
     * Store a new role
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'unique:roles,name'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        
        try {
            $role = $this->roleManagementService->createRole(
                $request->validated(),
                $request->user()
            );
            
            return $this->success(
                new RoleResource($role), 
                'Role created successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create role: ' . $e->getMessage());
            return $this->error('Failed to create role', 500);
        }
    }
    
    /**
     * Show a specific role
     */
    public function show(Role $role): JsonResponse
    {
        try {
            $role = $this->roleManagementService->getRoleById($role->id);
            
            return $this->success(new RoleResource($role));
        } catch (\Exception $e) {
            Log::error('Failed to fetch role: ' . $e->getMessage());
            return $this->error('Failed to fetch role', 500);
        }
    }
    
    /**
     * Update a specific role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'unique:roles,name,' . $role->id],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        
        try {
            $updatedRole = $this->roleManagementService->updateRole(
                $role,
                $request->validated(),
                $request->user()
            );
            
            return $this->success(
                new RoleResource($updatedRole), 
                'Role updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update role: ' . $e->getMessage());
            return $this->error('Failed to update role', 500);
        }
    }
    
    /**
     * Delete a specific role
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        try {
            $this->roleManagementService->deleteRole($role, $request->user());
            
            return $this->success(null, 'Role deleted successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\Exception $e) {
            Log::error('Failed to delete role: ' . $e->getMessage());
            return $this->error('Failed to delete role', 500);
        }
    }
}
