<?php

namespace App\Modules\Subscription\Repositories\Interfaces;

use App\Models\User;

interface SubscriptionRepositoryInterface
{
    public function isUserSubscribed(User $user): bool;

    public function isUserOnTrial(User $user): bool;

    public function startTrial(User $user, int $trialDays): bool;

    public function getActiveSubscriptionFromLocal(User $user): ?\App\Models\Subscription;

    public function hasLocalSubscriptionData(User $user): bool;
}
