<?php

namespace App\Modules\Subscription\Controllers;

use App\Modules\Auth\Resources\UserResource;
use App\Core\Traits\ApiResponse;
use App\Modules\Subscription\Requests\GetInvoiceRequest;
use App\Modules\Subscription\Requests\ListInvoicesRequest;
use App\Modules\Subscription\Requests\SubscribeRequest;
use App\Modules\Subscription\Requests\UpdatePaymentMethodRequest;
use App\Modules\Subscription\Requests\CheckoutRequest;
use App\Modules\Subscription\Requests\BillingPortalRequest;
use App\Modules\Subscription\Resources\InvoiceCollection;
use App\Modules\Subscription\Resources\InvoiceResource;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    use ApiResponse;
    
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    /**
     * Subscribe to a plan
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        if (!$request->user()->can('subscribe to plan')) {
            return $this->error('Unauthorized to subscribe', 403);
        }

        try {
            $result = $this->subscriptionService->subscribe(
                $request->user(),
                $request->validated()
            );
            
            return $this->success(
                new UserResource($result['user']), 
                'Subscription created successfully'
            );
        } catch (IncompletePayment $exception) {
            return $this->error(
                'Incomplete payment, please confirm your payment', 
                402, 
                ['payment_intent' => $exception->payment->id]
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Subscription error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'plan' => $request->plan,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to process subscription', 500);
        }
    }
    
    /**
     * Get current subscription
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $data = $this->subscriptionService->getSubscriptionStatus($request->user());
            return $this->success($data);
        } catch (\Exception $e) {
            Log::error('Failed to get subscription status: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve subscription information', 500);
        }
    }
    
    /**
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
    {
        if (!$request->user()->can('cancel subscription')) {
            return $this->error('Unauthorized to cancel subscription', 403);
        }

        try {
            $this->subscriptionService->cancelSubscription($request->user());
            return $this->success(null, 'Subscription has been cancelled');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to cancel subscription', 500);
        }
    }
    
    /**
     * Resume subscription
     */
    public function resume(Request $request): JsonResponse
    {
        if (!$request->user()->can('resume subscription')) {
            return $this->error('Unauthorized to resume subscription', 403);
        }

        try {
            $this->subscriptionService->resumeSubscription($request->user());
            return $this->success(null, 'Subscription has been resumed');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Subscription resume failed: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to resume subscription', 500);
        }
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
        
        try {
            $this->subscriptionService->startTrial($user);
            return $this->success(
                new UserResource($user->fresh('roles')), 
                'Trial started successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Failed to start trial: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to start trial', 500);
        }
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
    
    /**
     * Create Stripe Checkout session
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        if (!$request->user()->can('subscribe to plan')) {
            return $this->error('Unauthorized to subscribe', 403);
        }
        
        if ($this->subscriptionService->getSubscriptionStatus($request->user())['has_subscription']) {
            return $this->error('You already have an active subscription or trial', 403);
        }

        try {
            $result = $this->subscriptionService->createCheckoutSession(
                $request->user(),
                $request->validated()
            );
            
            return $this->success(['url' => $result['url']], 'Checkout session created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Checkout session creation error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'plan' => $request->plan ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create checkout session', 500);
        }
    }
    
    /**
     * Create Stripe Billing Portal session
     */
    public function billingPortal(BillingPortalRequest $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->createBillingPortalSession(
                $request->user(),
                $request->validated()
            );
            
            return $this->success(['url' => $result['url']], 'Billing portal session created successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Billing portal session creation error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create billing portal session', 500);
        }
    }
}
