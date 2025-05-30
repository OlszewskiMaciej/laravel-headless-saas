<?php

namespace App\Modules\Admin\Services;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}    /**
     * Get all users
     */
    public function getAllUsers(int $perPage = 15, array $filters = [], array $sorts = []): LengthAwarePaginator
    {
        return $this->userRepository->getAllPaginated($perPage, ['roles'], $filters, $sorts);
    }

    /**
     * Get paginated users with filters
     */
    public function getPaginatedUsers(int $perPage, array $filters = [], array $sorts = []): LengthAwarePaginator
    {
        return $this->userRepository->getAllPaginated($perPage, ['roles'], $filters, $sorts);
    }

    /**
     * Create a new user
     */
    public function createUser(array $data, User $creator): User
    {
        try {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ];

            $user = $this->userRepository->create($userData);
            
            if (isset($data['roles'])) {
                $this->userRepository->syncRoles($user, $data['roles']);
            }

            // Log activity
            activity()
                ->causedBy($creator)
                ->performedOn($user)
                ->withProperties(['roles' => $data['roles'] ?? []])
                ->log('created user');

            return $user->load('roles');
        } catch (\Exception $e) {
            Log::error('User creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update an existing user
     */
    public function updateUser(User $user, array $data, User $updater): User
    {
        try {
            // Update basic user data
            $this->userRepository->update($user, collect($data)->only(['name', 'email'])->toArray());
            
            // Update roles if specified
            if (isset($data['roles'])) {
                $roles = $data['roles'];
                
                // Check if user currently has admin role, preserve it if they do
                if ($user->hasRole('admin') && !in_array('admin', $roles)) {
                    $roles[] = 'admin';
                }
                
                $this->userRepository->syncRoles($user, $roles);
            }
            
            // Log activity
            activity()
                ->causedBy($updater)
                ->performedOn($user)
                ->log('updated user');
            
            return $user->fresh(['roles']);
        } catch (\Exception $e) {
            Log::error('User update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a user
     */
    public function deleteUser(User $user, User $deleter): bool
    {
        try {
            if ($user->id === $deleter->id) {
                throw new \InvalidArgumentException('You cannot delete your own account');
            }
            
            // Log activity before deletion
            activity()
                ->causedBy($deleter)
                ->performedOn($user)
                ->log('deleted user');
            
            return $this->userRepository->delete($user);
        } catch (\Exception $e) {
            Log::error('User deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get a user by ID
     */
    public function getUserById(string $id): ?User
    {
        return $this->userRepository->findById($id, ['roles']);
    }
}
