<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewUsers(User $user): bool
    {
        return $user->can('view users');
    }

    public function createUser(User $user): bool
    {
        return $user->can('create users');
    }

    public function updateUser(User $user): bool
    {
        return $user->can('update users');
    }

    public function deleteUser(User $user): bool
    {
        return $user->can('delete users');
    }
}
