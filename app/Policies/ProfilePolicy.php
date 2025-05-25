<?php

namespace App\Policies;

use App\Models\User;

class ProfilePolicy
{
    /**
     * Determine if the user can view their profile.
     */
    public function view(User $user): bool
    {
        return $user->can('view own profile');
    }

    /**
     * Determine if the user can update their profile.
     */
    public function update(User $user): bool
    {
        return $user->can('update own profile');
    }
}
