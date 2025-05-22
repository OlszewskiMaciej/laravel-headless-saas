<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Admin\Resources\RoleCollection;
use App\Modules\Admin\Resources\RoleResource;
use App\Modules\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController
{
    use ApiResponse;

    /**
     * List all roles
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        
        return $this->success(new RoleCollection($roles));
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
        
        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
        
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
        
        // Log activity
        activity()
            ->causedBy($request->user())
            ->performedOn($role)
            ->log('created role');
        
        return $this->success(
            new RoleResource($role->load('permissions')), 
            'Role created successfully'
        );
    }
    
    /**
     * Show a specific role
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');
        
        return $this->success(new RoleResource($role));
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
        
        if ($request->has('name')) {
            $role->update(['name' => $request->name]);
        }
        
        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }
        
        // Log activity
        activity()
            ->causedBy($request->user())
            ->performedOn($role)
            ->log('updated role');
        
        return $this->success(
            new RoleResource($role->fresh('permissions')), 
            'Role updated successfully'
        );
    }
    
    /**
     * Delete a specific role
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (in_array($role->name, ['admin', 'free', 'premium', 'trial'])) {
            return $this->error('Cannot delete a system role', 403);
        }
        
        // Log activity
        activity()
            ->causedBy($request->user())
            ->performedOn($role)
            ->log('deleted role');
        
        $role->delete();
        
        return $this->success(null, 'Role deleted successfully');
    }
}
