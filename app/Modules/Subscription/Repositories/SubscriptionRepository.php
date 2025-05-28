<?php

namespace App\Modules\Subscription\Repositories;

use App\Modules\Subscription\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    /**
     * Find user's subscription
     */
    public function findUserSubscription(User $user): ?Subscription
    {
        // Return active subscriptions first, then fall back to any subscription
        return $user->subscriptions()->active()->first() ?? 
               $user->subscriptions()->orderBy('created_at', 'desc')->first();
    }
    
    /**
     * Create a new subscription with transaction
     */
    public function createSubscription(User $user, array $data): Subscription
    {
        return DB::transaction(function () use ($user, $data) {
            return $user->subscriptions()->create($data);
        });
    }
    
    /**
     * Update subscription with transaction
     */
    public function updateSubscription(Subscription $subscription, array $data): bool
    {
        return DB::transaction(function () use ($subscription, $data) {
            return $subscription->update($data);
        });
    }
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->cancel();
            return true;
        });
    }
    
    /**
     * Resume subscription
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->resume();
            return true;
        });
    }    /**
     * Check if user is subscribed
     */
    public function isUserSubscribed(User $user): bool
    {
        // Check both the role and the actual subscription using active() query scope
        // A user with premium role or an active subscription in the database is considered subscribed
        return $user->hasRole('premium') || 
               $user->subscriptions()->active()->first() !== null;
    }
    
    /**
     * Check if user is on trial
     */
    public function isUserOnTrial(User $user): bool
    {
        // Use the subscriptions query builder to find subscriptions on trial
        return $user->hasRole('trial') || 
               $user->subscriptions()->onTrial()->first() !== null;
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
     * Find subscription by Stripe ID
     */
    public function findByStripeId(string $stripeId): ?Subscription
    {
        return Subscription::where('stripe_id', $stripeId)->first();
    }
    
    /**
     * Update subscription status
     */
    public function updateStatus(Subscription $subscription, string $status): bool
    {
        return DB::transaction(function () use ($subscription, $status) {
            $subscription->stripe_status = $status;
            return $subscription->save();
        });
    }
}
