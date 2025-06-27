<?php

namespace App\Modules\Subscription\Repositories;

use App\Modules\Subscription\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    /**
     * Check if user is subscribed
     */
    public function isUserSubscribed(User $user): bool
    {
        // Check if user has premium role or active trial
        // Users with active trials should be treated as subscribed (premium access)
        $hasActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();
        
        return $user->hasRole('premium') || 
               $user->hasRole('trial') ||
               $hasActiveTrial;
    }
      
    /**
     * Check if user is on trial
     */
    public function isUserOnTrial(User $user): bool
    {
        // Check if user has trial role or active local trial
        $hasActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();
        
        return $user->hasRole('trial') || 
               $hasActiveTrial;
    }
      
    /**
     * Start trial for user
     */
    public function startTrial(User $user, int $trialDays): bool
    {
        return DB::transaction(function () use ($user, $trialDays) {
            $user->trial_ends_at = Carbon::now()->addDays($trialDays);
            return $user->save();
        });
    }

    /**
     * Get user's active subscription from local database
     */
    public function getActiveSubscriptionFromLocal(User $user): ?\App\Models\Subscription
    {
        if (!$user->stripe_id) {
            return null;
        }

        return $user->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if user has any subscription records in local database
     */
    public function hasLocalSubscriptionData(User $user): bool
    {
        if (!$user->stripe_id) {
            return false;
        }

        return $user->subscriptions()->exists();
    }
}
