<?php

namespace App\Modules\User\Controllers;

use App\Modules\Auth\Resources\UserResource;
use App\Modules\Core\Traits\ApiResponse;
use App\Modules\User\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Controller as BaseController;

class ProfileController extends BaseController
{
    use ApiResponse;

    /**
     * Get authenticated user profile
     */
    public function show(Request $request): JsonResponse
    {
        if (Gate::denies('view', $request->user())) {
            return $this->error('Unauthorized to view profile', 403);
        }

        return $this->success(new UserResource($request->user()));
    }
    
    /**
     * Update authenticated user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        if (Gate::denies('update', $request->user())) {
            return $this->error('Unauthorized to update profile', 403);
        }

        $user = $request->user();
        $validated = $request->validated();
        
        // Handle password change if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            
            // Remove the current_password field as it's not needed for the update
            unset($validated['current_password']);
            
            // Log password change separately
            activity()->causedBy($user)->log('changed password');
        }

        $user->update($validated);
        
        // Log profile update
        activity()->causedBy($user)->log('updated profile');
        
        return $this->success(
            new UserResource($user->fresh()), 
            'Profile updated successfully'
        );
    }
}
