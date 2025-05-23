<?php

namespace App\Modules\Admin\Controllers;

use App\Models\User;
use App\Modules\Admin\Requests\CreateUserRequest;
use App\Modules\Admin\Requests\UpdateUserRequest;
use App\Modules\Admin\Resources\UserCollection;
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Spatie\QueryBuilder\QueryBuilder;

class UserController
{
    use ApiResponse;

    /**
     * List all users
     */
    public function index(Request $request): JsonResponse
    {
        if (Gate::denies('viewUsers', User::class)) {
            return $this->error('Unauthorized to view users', 403);
        }

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(['name', 'email'])
            ->allowedSorts(['name', 'email', 'created_at'])
            ->with('roles')
            ->paginate($request->per_page ?? 15)
            ->appends($request->query());
        
        return $this->success(new UserCollection($users));
    }

    /**
     * Store a new user
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        if (Gate::denies('createUser', User::class)) {
            return $this->error('Unauthorized to create users', 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->syncRoles($request->roles);

        // Log activity
        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->withProperties(['roles' => $request->roles])
            ->log('created user');

        return $this->success(
            new UserResource($user->load('roles')), 
            'User created successfully'
        );
    }
    
    /**
     * Get a specific user
     */
    public function show(User $user): JsonResponse
    {
        if (Gate::denies('viewUsers', User::class)) {
            return $this->error('Unauthorized to view user', 403);
        }

        $user->load(['roles']);
        
        return $this->success(new UserResource($user));
    }
    
    /**
     * Update a specific user
     */    
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (Gate::denies('updateUser', User::class)) {
            return $this->error('Unauthorized to update user', 403);
        }

        $user->update($request->only(['name', 'email']));
        
        // Update roles if specified
        if ($request->has('roles')) {
            // Check if user currently has admin role, preserve it if they do
            if ($user->hasRole('admin')) {
                $roles = $request->roles;
                if (!in_array('admin', $roles)) {
                    $roles[] = 'admin';
                }
                $user->syncRoles($roles);
            } else {
                $user->syncRoles($request->roles);
            }
        }
        
        // Log activity
        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->log('updated user');
        
        return $this->success(
            new UserResource($user->fresh('roles')), 
            'User updated successfully'
        );
    }
    
    /**
     * Delete a specific user
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (Gate::denies('deleteUser', User::class)) {
            return $this->error('Unauthorized to delete user', 403);
        }

        if ($user->id === $request->user()->id) {
            return $this->error('You cannot delete your own account', 403);
        }
        
        // Log activity
        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->log('deleted user');
        
        $user->delete();
        
        return $this->success(null, 'User deleted successfully');
    }
}
