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
     * Subscribe user to a plan
     */
    public function subscribe(User $user, array $data): array
    {
        try {
            // Get plan details from config
            $plans = config('subscription.plans');
            $planName = $data['plan'];
            
            if (!isset($plans[$planName])) {
                throw new \InvalidArgumentException('Invalid plan selected');
            }
            
            $plan = $plans[$planName];
            
            // Check if user already has an active subscription
            if ($this->subscriptionRepository->isUserSubscribed($user)) {
                throw new \InvalidArgumentException('You already have an active subscription');
            }            // Create Stripe subscription
            $subscription = $user->newSubscription($planName, $plan['stripe_id'])
                ->create($data['payment_method'] ?? null);
            
            // Update user role - assign premium role (preserving admin if they have it)
            $roles = $user->hasRole('admin') ? ['admin', 'premium'] : ['premium'];
            $this->userRepository->syncRoles($user, $roles);
            
            // Log activity
            activity()
                ->causedBy($user)
                ->withProperties(['plan' => $planName])
                ->log('subscribed to plan');
            
            return [
                'user' => $user->fresh(['roles']),
                'subscription' => $subscription
            ];
        } catch (IncompletePayment $e) {
            Log::error('Incomplete payment during subscription: ' . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            Log::error('Subscription failed: ' . $e->getMessage());
            throw $e;
        }
    }    
    
    /**
     * Get subscription status
     */      public function getSubscriptionStatus(User $user): array
    {
        if (!$this->subscriptionRepository->isUserSubscribed($user) && !$this->subscriptionRepository->isUserOnTrial($user)) {
            return [
                'status' => 'free',
                'on_trial' => false,
            ];
        }
        
        // Get the subscription using the repository method that we've improved
        $subscription = $this->subscriptionRepository->findUserSubscription($user);
        
        $data = [
            'status' => $subscription ? $subscription->stripe_status : 'no_subscription',
            'on_trial' => $this->subscriptionRepository->isUserOnTrial($user),
            'trial_ends_at' => $user->trial_ends_at,
        ];
        
        if ($subscription) {
            $data['plan'] = $subscription->name;
            $data['ends_at'] = $subscription->ends_at;
            $data['canceled'] = $subscription->canceled();
        }
        
        return $data;
    }   
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription(User $user): bool
    {
        try {
            if (!$this->subscriptionRepository->isUserSubscribed($user)) {
                throw new \InvalidArgumentException('You do not have an active subscription');
            }
            
            $subscription = $this->subscriptionRepository->findUserSubscription($user);
            $this->subscriptionRepository->cancelSubscription($subscription);
            
            // Log activity
            activity()->causedBy($user)->log('cancelled subscription');
            
            return true;
        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription(User $user): bool
    {
        try {
            $subscription = $this->subscriptionRepository->findUserSubscription($user);
            
            if (!$subscription || !$subscription->canceled()) {
                throw new \InvalidArgumentException('Subscription cannot be resumed');
            }
            
            $this->subscriptionRepository->resumeSubscription($subscription);
            
            // Log activity
            activity()->causedBy($user)->log('resumed subscription');
            
            return true;
        } catch (\Exception $e) {
            Log::error('Subscription resume failed: ' . $e->getMessage());
            throw $e;
        }
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
            
            // Check if user already has an active premium subscription
            if ($this->subscriptionRepository->isUserSubscribed($user)) {
                throw new \InvalidArgumentException('You already have a premium subscription, no need for a trial');
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
     * Update payment method
     */
    public function updatePaymentMethod(User $user, string $paymentMethod): bool
    {
        try {
            $user->updateDefaultPaymentMethod($paymentMethod);
            
            // Log activity
            activity()->causedBy($user)->log('updated payment method');
            
            return true;
        } catch (\Exception $e) {
            Log::error('Payment method update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get invoice
     */
    public function getInvoice(User $user, string $invoiceId): array
    {
        try {
            $invoice = $user->findInvoice($invoiceId);
            
            if (!$invoice) {
                throw new \InvalidArgumentException('Invoice not found');
            }
            
            return ['invoice' => $invoice];
        } catch (\Exception $e) {
            Log::error('Invoice retrieval failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * List invoices
     */
    public function listInvoices(User $user): array
    {
        try {
            $invoices = $user->invoices();
            return ['invoices' => $invoices];
        } catch (\Exception $e) {
            Log::error('Invoice listing failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
