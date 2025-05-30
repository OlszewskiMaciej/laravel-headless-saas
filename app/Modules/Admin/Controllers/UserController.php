<?php

namespace App\Modules\Admin\Controllers;

use App\Models\User;
use App\Modules\Admin\Requests\CreateUserRequest;
use App\Modules\Admin\Requests\UpdateUserRequest;
use App\Modules\Admin\Resources\UserCollection;
use App\Modules\Admin\Services\UserManagementService;
use App\Modules\Auth\Resources\UserResource;
use App\Core\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class UserController
{
    use ApiResponse;

    public function __construct(
        private readonly UserManagementService $userManagementService
    ) {}

    /**
     * List all users
     */
    public function index(Request $request): JsonResponse
    {
        if (Gate::denies('viewUsers', User::class)) {
            return $this->error('Unauthorized to view users', 403);
        }

        try {
            $users = $this->userManagementService->getAllUsers(
                $request->per_page ?? 15,
                $request->only(['name', 'email']), // filters
                $request->only(['sort']) // sorts
            );
            
            return $this->success(new UserCollection($users));
        } catch (\Exception $e) {
            Log::error('Failed to fetch users: ' . $e->getMessage());
            return $this->error('Failed to fetch users', 500);
        }
    }

    /**
     * Store a new user
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        if (Gate::denies('createUser', User::class)) {
            return $this->error('Unauthorized to create users', 403);
        }

        try {
            $user = $this->userManagementService->createUser(
                $request->validated(),
                $request->user()
            );

            return $this->success(
                new UserResource($user), 
                'User created successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to create user: ' . $e->getMessage());
            return $this->error('Failed to create user', 500);
        }
    }
    
    /**
     * Get a specific user
     */
    public function show(User $user): JsonResponse
    {
        if (Gate::denies('viewUsers', User::class)) {
            return $this->error('Unauthorized to view user', 403);
        }

        try {
            $user = $this->userManagementService->getUserById($user->id);
            
            return $this->success(new UserResource($user));
        } catch (\Exception $e) {
            Log::error('Failed to fetch user: ' . $e->getMessage());
            return $this->error('Failed to fetch user', 500);
        }
    }
    
    /**
     * Update a specific user
     */    
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (Gate::denies('updateUser', User::class)) {
            return $this->error('Unauthorized to update user', 403);
        }

        try {
            $updatedUser = $this->userManagementService->updateUser(
                $user,
                $request->validated(),
                $request->user()
            );
            
            return $this->success(
                new UserResource($updatedUser), 
                'User updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update user: ' . $e->getMessage());
            return $this->error('Failed to update user', 500);
        }
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

        try {
            $this->userManagementService->deleteUser($user, $request->user());
            
            return $this->success(null, 'User deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete user: ' . $e->getMessage());
            return $this->error('Failed to delete user', 500);
        }
    }
}
