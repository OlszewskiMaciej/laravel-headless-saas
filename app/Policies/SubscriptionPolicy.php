<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    public function subscribe(User $user): bool
    {
        return true; // All authenticated users can subscribe
    }

    public function cancel(User $user): bool
    {
        return true; // All authenticated users can cancel their subscription
    }

    public function resume(User $user): bool
    {
        return true; // All authenticated users can resume their subscription
    }

    public function startTrial(User $user): bool
    {
        // All authenticated users can attempt to start a trial
        // Business rules are handled in the controller
        return true;
    }

    public function viewSubscription(User $user): bool
    {
        return true; // All authenticated users can view their subscription status
    }
}
