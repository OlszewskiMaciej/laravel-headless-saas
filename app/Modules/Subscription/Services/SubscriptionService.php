<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Subscription\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Models\User;
use App\Core\Enums\SubscriptionStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {}

     /**
     * Get subscription status
     */      
    public function getSubscriptionStatus(User $user): array
    {
        // Check if user has active trial first
        $isOnActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();
        
        // Jeśli użytkownik nie ma stripe_id, sprawdź tylko trial lokalny
        if (!$user->stripe_id) {
            return [
                'status' => $isOnActiveTrial ? 'trial' : 'free',
                'has_subscription' => $isOnActiveTrial, // Treat active trial as having subscription
                'on_trial' => $isOnActiveTrial,
                'trial_ends_at' => $user->trial_ends_at,
                'source' => 'local'
            ];
        }

        // Sprawdź najpierw lokalne subskrypcje
        $localSubscription = $this->subscriptionRepository->findUserSubscription($user);
        
        // Check if user has active trial
        $isOnActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();
        
        // Jeśli mamy lokalną subskrypcję, użyj jej
        if ($localSubscription && $localSubscription->active()) {
            return [
                'status' => $localSubscription->stripe_status,
                'has_subscription' => true,
                'on_trial' => $localSubscription->onTrial(),
                'trial_ends_at' => $localSubscription->trial_ends_at,
                'plan' => $localSubscription->name,
                'ends_at' => $localSubscription->ends_at,
                'canceled' => $localSubscription->canceled(),
                'stripe_subscription_id' => $localSubscription->stripe_id,
                'source' => 'local'
            ];
        }
        
        // If user has active trial but no local subscription, treat as premium trial user
        if ($isOnActiveTrial) {
            return [
                'status' => 'trial',
                'has_subscription' => true, // Treat active trial as having subscription
                'on_trial' => true,
                'trial_ends_at' => $user->trial_ends_at,
                'plan' => 'trial',
                'source' => 'local'
            ];
        }

        // Jeśli nie ma lokalnej subskrypcji, sprawdź bezpośrednio w Stripe
        return $this->getSubscriptionStatusFromStripe($user);
    }    
    
    /**
     * Get subscription status directly from Stripe API
     */
    public function getSubscriptionStatusFromStripe(User $user): array
    {
        try {
            // Check if user has active trial first
            $isOnActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();

            if (!$user->stripe_id) {
                return [
                    'status' => $isOnActiveTrial ? 'trial' : 'free',
                    'has_subscription' => $isOnActiveTrial, // Treat active trial as having subscription
                    'on_trial' => $isOnActiveTrial,
                    'trial_ends_at' => $user->trial_ends_at,
                    'source' => 'stripe_fallback'
                ];
            }
            
            // Pobierz subskrypcje ze Stripe
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $user->stripe_id,
                'status' => 'all',
                'limit' => 10,
            ]);

            $activeSubscription = null;
            foreach ($subscriptions->data as $subscription) {
                if (in_array($subscription->status, ['active', 'trialing', 'past_due'])) {
                    $activeSubscription = $subscription;
                    break;
                }
            }

            if (!$activeSubscription) {
                // Check if user has active trial
                $isOnActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();
                
                return [
                    'status' => $isOnActiveTrial ? 'trial' : 'free',
                    'has_subscription' => $isOnActiveTrial, // Treat active trial as having subscription
                    'on_trial' => $isOnActiveTrial,
                    'trial_ends_at' => $user->trial_ends_at,
                    'source' => 'stripe'
                ];
            }

            return [
                'status' => $this->mapStripeStatus($activeSubscription->status),
                'has_subscription' => true,
                'stripe_status' => $activeSubscription->status,
                'stripe_subscription_id' => $activeSubscription->id,
                'on_trial' => $activeSubscription->status === 'trialing',
                'trial_ends_at' => $activeSubscription->trial_end ? 
                    \Carbon\Carbon::createFromTimestamp($activeSubscription->trial_end) : null,
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($activeSubscription->current_period_start),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp($activeSubscription->current_period_end),
                'cancel_at_period_end' => $activeSubscription->cancel_at_period_end,
                'plan_name' => $activeSubscription->items->data[0]->price->nickname ?? ' ',
                'plan_amount' => $activeSubscription->items->data[0]->price->unit_amount / 100,
                'plan_currency' => $activeSubscription->items->data[0]->price->currency,
                'plan_interval' => $activeSubscription->items->data[0]->price->recurring->interval,
                'source' => 'stripe'
            ];

        } catch (\Exception $e) {
                Log::error('Failed to get subscription status from Stripe: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'stripe_id' => $user->stripe_id,
                ]);

            // Even on error, check for active trial
            $isOnActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();

            return [
                'status' => $isOnActiveTrial ? 'trial' : 'unknown',
                'has_subscription' => $isOnActiveTrial, // Treat active trial as having subscription
                'error' => 'Failed to retrieve subscription data',
                'on_trial' => $isOnActiveTrial,
                'trial_ends_at' => $user->trial_ends_at,
                'source' => 'error'
            ];
        }
    }

    /**
     * Map Stripe status to our internal status
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match($stripeStatus) {
            'active' => 'active',
            'trialing' => 'trial',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'canceled',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'expired',
            default => 'unknown'
        };
    }

    /**
     * Start trial
     */
    public function startTrial(User $user): bool
    {
        try {
            if ($this->subscriptionRepository->isUserOnTrial($user) || $user->trial_ends_at !== null) {
                throw new \InvalidArgumentException('You have already used your trial period');
            }
            
            if ($this->subscriptionRepository->isUserSubscribed($user)) {
                throw new \InvalidArgumentException('You already have a premium subscription or active trial');
            }
            
            $trialDays = config('subscription.trial_days', 30);
            $this->subscriptionRepository->startTrial($user, $trialDays);
            
            // Assign trial role, preserving admin role if user has it
            $roles = $user->hasRole('admin') ? ['admin', 'trial'] : ['trial'];
            $this->userRepository->syncRoles($user, $roles);
            
            // Log activity
            activity()
                ->causedBy($user)
                ->withProperties(['trial_days' => $trialDays])
                ->log('started trial');
            
            return true;
        } catch (\Exception $e) {
            Log::error('Trial start failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a Stripe Checkout session for subscription
     */
    public function createCheckoutSession(User $user, array $data): array
    {
        try {
            // Validate plan if provided
            if (isset($data['plan'])) {
                $plans = config('subscription.plans');
                $planName = $data['plan'];
                
                if (!isset($plans[$planName])) {
                    throw new \InvalidArgumentException('Invalid plan selected');
                }
                
                $plan = $plans[$planName];
            }
            
            // Check if user is subscribing to a plan or starting a trial
            $mode = $data['mode'] ?? 'subscription';
            
            // Create success and cancel URLs
            $successUrl = $data['success_url'] ?? config('app.frontend_url') . '/subscription/success';
            $cancelUrl = $data['cancel_url'] ?? config('app.frontend_url') . '/subscription/cancel';
            
            // Build checkout session parameters
            $checkoutParams = [
                'customer' => $this->getStripeCustomerId($user),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'mode' => $mode,
            ];
            
            // For subscription mode, add line items based on the selected plan
            if ($mode === 'subscription' && isset($data['plan'])) {
                $checkoutParams['line_items'] = [[
                    'price' => $plan['stripe_id'],
                    'quantity' => 1,
                ]];
            }
            
            // Add trial days if specified
            if (isset($data['trial_days']) && $data['trial_days'] > 0) {
                $checkoutParams['subscription_data'] = [
                    'trial_period_days' => $data['trial_days'],
                ];
            }
            
            // Create the Stripe Checkout session
            $session = \Stripe\Checkout\Session::create($checkoutParams);
            
            // Log activity
            activity()
                ->causedBy($user)
                ->withProperties([
                    'plan' => $data['plan'] ?? null,
                    'mode' => $mode,
                    'session_id' => $session->id,
                ])
                ->log('created checkout session');
            
            return [
                'url' => $session->url,
                'session_id' => $session->id,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Checkout session: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Create a Stripe Billing Portal session
     */
    public function createBillingPortalSession(User $user, array $data = []): array
    {
        try {
            // Ensure user has a Stripe customer ID
            if (!$user->stripe_id) {
                throw new \InvalidArgumentException('User does not have a Stripe customer ID');
            }
            
            // Set return URL (where the user will be redirected after leaving the portal)
            $returnUrl = $data['return_url'] ?? config('app.frontend_url') . '/account';
            
            // Create Billing Portal session
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $user->stripe_id,
                'return_url' => $returnUrl,
            ]);
            
            // Log activity
            activity()
                ->causedBy($user)
                ->withProperties(['session_id' => $session->id])
                ->log('accessed billing portal');
            
            return [
                'url' => $session->url,
                'session_id' => $session->id,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Billing Portal session: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Get or create a Stripe customer ID for the user
     */
    protected function getStripeCustomerId(User $user): string
    {
        // If user already has a Stripe customer ID, return it
        if ($user->stripe_id) {
            return $user->stripe_id;
        }
        
        // Otherwise, create a customer in Stripe and return the ID
        $customer = $user->createAsStripeCustomer([
            'email' => $user->email,
            'name' => $user->name,
        ]);
        
        return $customer->id;
    }
}
