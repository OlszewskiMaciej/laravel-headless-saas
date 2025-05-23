<?php

namespace App\Modules\Subscription\Controllers;

use App\Modules\Auth\Resources\UserResource;
use App\Modules\Core\Traits\ApiResponse;
use App\Modules\Subscription\Requests\SubscribeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionController
{
    use ApiResponse;

    /**
     * Subscribe to a plan
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        if (!$request->user()->can('subscribe to plan')) {
            return $this->error('Unauthorized to subscribe', 403);
        }

        try {
            $user = $request->user();
            
            // Get plan details from config
            $plans = config('subscription.plans');
            if (!isset($plans[$request->plan])) {
                return $this->error('Invalid subscription plan', 422);
            }
            
            $plan = $plans[$request->plan];
            
            // If user already has a subscription, swap it
            if ($user->subscribed()) {
                $user->subscription()->swap($plan['stripe_id']);
                
                // Preserve admin role if user has it
                if ($user->hasRole('admin')) {
                    $user->syncRoles(['admin', 'premium']);
                } else {
                    $user->syncRoles(['premium']);
                }
                
                // Log activity
                activity()
                    ->causedBy($user)
                    ->withProperties(['plan' => $plan['name']])
                    ->log('changed subscription plan');
                
                return $this->success(
                    new UserResource($user->fresh('subscriptions')), 
                    'Subscription plan changed successfully'
                );
            }
            
            // Create new subscription
            $subscription = $user->newSubscription('default', $plan['stripe_id'])
                ->create($request->payment_method);
            
            // Update user's role to premium, preserving admin if they have it
            if ($user->hasRole('admin')) {
                $user->syncRoles(['admin', 'premium']);
            } else {
                $user->syncRoles(['premium']);
            }
            
            // Log activity
            activity()
                ->causedBy($user)
                ->withProperties(['plan' => $plan['name']])
                ->log('subscribed to plan');
            
            return $this->success(
                new UserResource($user->fresh('subscriptions')), 
                'Subscription created successfully'
            );
        } catch (IncompletePayment $exception) {
            return $this->error(
                'Incomplete payment, please confirm your payment', 
                402, 
                ['payment_intent' => $exception->payment->id]
            );
        } catch (\Exception $e) {
            Log::error('Subscription error: ' . $e->getMessage());
            return $this->error('Failed to process subscription: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get current subscription
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->subscribed() && !$user->onTrial()) {
            return $this->success([
                'status' => 'free',
                'on_trial' => false,
            ]);
        }
        
        $data = [
            'status' => $user->subscription() ? $user->subscription()->stripe_status : 'no_subscription',
            'on_trial' => $user->onTrial(),
            'trial_ends_at' => $user->trial_ends_at,
        ];
        
        if ($user->subscription()) {
            $data['plan'] = $user->subscription()->name;
            $data['ends_at'] = $user->subscription()->ends_at;
            $data['canceled'] = $user->subscription()->canceled();
        }
        
        return $this->success($data);
    }
    
    /**
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        if (!$request->user()->can('cancel subscription')) {
            return $this->error('Unauthorized to cancel subscription', 403);
        }

        $user = $request->user();
        
        if (!$user->subscribed()) {
            return $this->error('You do not have an active subscription', 400);
        }
        
        $user->subscription()->cancel();
        
        // Log activity
        activity()->causedBy($user)->log('cancelled subscription');
        
        return $this->success(null, 'Subscription has been cancelled');
    }
    
    /**
     * Resume subscription
     */
    public function resume(Request $request): JsonResponse
    {
        if (!$request->user()->can('resume subscription')) {
            return $this->error('Unauthorized to resume subscription', 403);
        }

        $user = $request->user();
        
        if (!$user->subscription() || !$user->subscription()->canceled()) {
            return $this->error('Subscription cannot be resumed', 400);
        }
        
        $user->subscription()->resume();
        
        // Log activity
        activity()->causedBy($user)->log('resumed subscription');
        
        return $this->success(null, 'Subscription has been resumed');
    }
    
    /**
     * Start free trial
     */    
    public function startTrial(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->can('start trial')) {
            return $this->error('Unauthorized to start trial', 403);
        }
        
        if ($user->onTrial() || $user->trial_ends_at !== null) {
            return $this->error('You have already used your trial period', 400);
        }
        
        // Check if user already has an active premium subscription
        if ($user->subscribed()) {
            return $this->error('You already have a premium subscription, no need for a trial', 400);
        }
        
        $trialDays = config('subscription.trial_days', 30);
        $user->trial_ends_at = Carbon::now()->addDays($trialDays);
        $user->save();
        
        // Assign trial role, preserving admin role if user has it
        if ($user->hasRole('admin')) {
            $user->syncRoles(['admin', 'trial']);
        } else {
            $user->syncRoles(['trial']);
        }
        
        // Log activity
        activity()
            ->causedBy($user)
            ->withProperties(['days' => $trialDays])
            ->log('started trial');
        
        return $this->success(
            new UserResource($user), 
            "Your {$trialDays}-day trial has started"
        );
    }
}
