<?php

namespace App\Modules\Subscription\Repositories\Interfaces;

use App\Models\User;
use App\Models\Subscription;

interface SubscriptionRepositoryInterface
{
    public function findUserSubscription(User $user): ?Subscription;
    
    public function createSubscription(User $user, array $data): Subscription;
    
    public function updateSubscription(Subscription $subscription, array $data): bool;
    
    public function cancelSubscription(Subscription $subscription): bool;
    
    public function resumeSubscription(Subscription $subscription): bool;
    
    public function isUserSubscribed(User $user): bool;
}
