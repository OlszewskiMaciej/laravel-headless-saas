<?php

namespace App\Modules\Subscription\Controllers;

use App\Modules\Auth\Resources\UserResource;
use App\Core\Traits\ApiResponse;
use App\Modules\Subscription\Requests\CheckoutRequest;
use App\Modules\Subscription\Requests\BillingPortalRequest;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {
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
                'user_uuid' => $request->user()->uuid,
                'trace'     => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve subscription information', 500);
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
                'user_uuid' => $user->uuid,
                'trace'     => $e->getTraceAsString()
            ]);
            return $this->error('Failed to start trial', 500);
        }
    }

    /**
     * Create Stripe Checkout session
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
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
                'user_uuid' => $request->user()->uuid,
                'plan'      => $request->plan ?? null,
                'trace'     => $e->getTraceAsString()
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
                'user_uuid' => $request->user()->uuid,
                'trace'     => $e->getTraceAsString()
            ]);
            return $this->error('Failed to create billing portal session', 500);
        }
    }

    /**
     * Get available currencies
     */
    public function currencies(Request $request): JsonResponse
    {
        try {
            $currencies = $this->subscriptionService->getAvailableCurrencies();
            return $this->success($currencies, 'Available currencies retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to get available currencies: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve available currencies', 500);
        }
    }

    /**
     * Get available plans
     */
    public function plans(Request $request): JsonResponse
    {
        try {
            $currency = $request->get('currency');
            $plans    = $this->subscriptionService->getAvailablePlans($currency);
            return $this->success($plans, 'Available plans retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to get available plans: ' . $e->getMessage(), [
                'currency' => $request->get('currency'),
                'trace'    => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve available plans', 500);
        }
    }
}
