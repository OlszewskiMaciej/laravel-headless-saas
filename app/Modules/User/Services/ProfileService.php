<?php

namespace App\Modules\User\Services;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): User
    {
        try {
            $updateData = collect($data)->only(['name', 'email'])->toArray();
            
            // Hash password if provided
            if (isset($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $this->userRepository->update($user, $updateData);

            // Log activity
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->log('updated profile');

            return $user->fresh(['roles']);
        } catch (\Exception $e) {
            Log::error('Profile update failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
