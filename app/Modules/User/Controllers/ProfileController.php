<?php

namespace App\Modules\User\Controllers;

use App\Modules\Auth\Resources\UserResource;
use App\Core\Traits\ApiResponse;
use App\Modules\User\Requests\UpdateProfileRequest;
use App\Modules\User\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller as BaseController;

class ProfileController extends BaseController
{
    use ApiResponse;

    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    /**
     * Get authenticated user profile
     */
    public function show(Request $request): JsonResponse
    {
        try {
            return $this->success(new UserResource($request->user()));
        } catch (\Exception $e) {
            Log::error('Failed to get user profile: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to retrieve profile', 500);
        }
    }
    
    /**
     * Update authenticated user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $updatedUser = $this->profileService->updateProfile($user, $validated);
            
            return $this->success(
                new UserResource($updatedUser), 
                'Profile updated successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to update user profile: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error('Failed to update profile', 500);
        }
    }
}
