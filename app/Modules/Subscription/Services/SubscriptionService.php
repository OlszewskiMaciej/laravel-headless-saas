<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Subscription\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

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
                'status'           => $isOnActiveTrial ? 'trial' : 'free',
                'has_subscription' => $isOnActiveTrial, // Treat active trial as having subscription
                'on_trial'         => $isOnActiveTrial,
                'trial_ends_at'    => $user->trial_ends_at,
                'plan'             => $isOnActiveTrial ? 'trial' : 'free',
                'source'           => 'local'
            ];
        }

        // If user has active trial, treat as premium trial user
        if ($isOnActiveTrial) {
            return [
                'status'           => 'trial',
                'has_subscription' => true, // Treat active trial as having subscription
                'on_trial'         => true,
                'trial_ends_at'    => $user->trial_ends_at,
                'plan'             => 'trial',
                'source'           => 'local'
            ];
        }

        // Check subscription status directly from Stripe
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
                    'status'           => $isOnActiveTrial ? 'trial' : 'free',
                    'has_subscription' => $isOnActiveTrial, // Treat active trial as having subscription
                    'on_trial'         => $isOnActiveTrial,
                    'trial_ends_at'    => $user->trial_ends_at,
                    'source'           => 'stripe_fallback'
                ];
            }

            // Pobierz subskrypcje ze Stripe (źródło prawdy)
            $subscriptions = \Stripe\Subscription::all([
                'customer' => $user->stripe_id,
                'status'   => 'all',
                'limit'    => 10,
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
                    'status'           => $isOnActiveTrial ? 'trial' : 'free',
                    'has_subscription' => $isOnActiveTrial, // Treat active trial as having subscription
                    'on_trial'         => $isOnActiveTrial,
                    'trial_ends_at'    => $user->trial_ends_at,
                    'source'           => 'stripe'
                ];
            }

            return [
                'status'                 => $this->mapStripeStatus($activeSubscription->status),
                'has_subscription'       => true,
                'stripe_status'          => $activeSubscription->status,
                'stripe_subscription_id' => $activeSubscription->id,
                'on_trial'               => $activeSubscription->status === 'trialing',
                'trial_ends_at'          => $activeSubscription->trial_end ?
                    \Carbon\Carbon::createFromTimestamp($activeSubscription->trial_end) : null,
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($activeSubscription->current_period_start),
                'current_period_end'   => \Carbon\Carbon::createFromTimestamp($activeSubscription->current_period_end),
                'cancel_at_period_end' => $activeSubscription->cancel_at_period_end,
                'plan_name'            => $activeSubscription->items->data[0]->price->nickname ?? ' ',
                'plan_amount'          => $activeSubscription->items->data[0]->price->unit_amount / 100,
                'plan_currency'        => $activeSubscription->items->data[0]->price->currency,
                'plan_interval'        => $activeSubscription->items->data[0]->price->recurring->interval,
                'source'               => 'stripe'
            ];

        } catch (\Exception $e) {
            // Check if fallback is enabled before proceeding
            $fallbackEnabled   = config('subscription.fallback.enabled', true);
            $logFallbackEvents = config('subscription.fallback.log_fallback_events', true);

            if ($logFallbackEvents) {
                Log::error('Failed to get subscription status from Stripe: ' . $e->getMessage(), [
                    'user_uuid'        => $user->uuid,
                    'stripe_id'        => $user->stripe_id,
                    'error'            => $e->getMessage(),
                    'fallback_enabled' => $fallbackEnabled
                ]);
            }

            // Only fallback if enabled in configuration
            if (!$fallbackEnabled) {
                throw $e; // Re-throw the error if fallback is disabled
            }

            if ($logFallbackEvents) {
                Log::info('Falling back to local database for subscription status', [
                    'user_uuid' => $user->uuid,
                    'stripe_id' => $user->stripe_id
                ]);
            }

            return $this->getSubscriptionStatusFromLocalDatabase($user);
        }
    }

    /**
     * Get subscription status from local database (fallback method)
     */
    private function getSubscriptionStatusFromLocalDatabase(User $user): array
    {
        try {
            // Check data age configuration
            $maxDataAgeHours   = config('subscription.fallback.max_local_data_age_hours', 24);
            $logFallbackEvents = config('subscription.fallback.log_fallback_events', true);

            // Check if user has active trial first
            $isOnActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();

            if (!$user->stripe_id) {
                return [
                    'status'           => $isOnActiveTrial ? 'trial' : 'free',
                    'has_subscription' => $isOnActiveTrial,
                    'on_trial'         => $isOnActiveTrial,
                    'trial_ends_at'    => $user->trial_ends_at,
                    'source'           => 'local_database'
                ];
            }

            // Znajdź aktywną subskrypcję w lokalnej bazie danych
            $activeSubscription = $user->subscriptions()
                ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$activeSubscription) {
                return [
                    'status'           => $isOnActiveTrial ? 'trial' : 'free',
                    'has_subscription' => $isOnActiveTrial,
                    'on_trial'         => $isOnActiveTrial,
                    'trial_ends_at'    => $user->trial_ends_at,
                    'source'           => 'local_database'
                ];
            }

            // Check if local data is stale based on configuration
            $dataAge = $activeSubscription->updated_at->diffInHours(now());
            $isStale = $dataAge > $maxDataAgeHours;

            if ($isStale && $logFallbackEvents) {
                Log::warning('Local subscription data is stale', [
                    'user_uuid'       => $user->uuid,
                    'subscription_id' => $activeSubscription->stripe_id,
                    'data_age_hours'  => $dataAge,
                    'max_age_hours'   => $maxDataAgeHours
                ]);
            }

            // Pobierz pierwszy item z subskrypcji dla informacji o planie
            $subscriptionItem = $activeSubscription->items()->first();

            $note = 'Data retrieved from local database due to Stripe API unavailability';
            if ($isStale) {
                $note .= " (Warning: Local data is {$dataAge} hours old, may be stale)";
            }

            return [
                'status'                 => $this->mapStripeStatus($activeSubscription->stripe_status),
                'has_subscription'       => true,
                'stripe_status'          => $activeSubscription->stripe_status,
                'stripe_subscription_id' => $activeSubscription->stripe_id,
                'on_trial'               => $activeSubscription->stripe_status === 'trialing',
                'trial_ends_at'          => $activeSubscription->trial_ends_at,
                'current_period_start'   => $activeSubscription->created_at, // Przybliżone, bo nie mamy current_period_start w lokalnej bazie
                'current_period_end'     => $activeSubscription->ends_at,
                'cancel_at_period_end'   => $activeSubscription->ends_at !== null,
                'plan_name'              => $subscriptionItem ? $subscriptionItem->stripe_price : null,
                'plan_amount'            => null, // Nie mamy tej informacji w lokalnej bazie
                'plan_currency'          => null, // Nie mamy tej informacji w lokalnej bazie
                'plan_interval'          => null, // Nie mamy tej informacji w lokalnej bazie
                'source'                 => 'local_database',
                'note'                   => $note,
                'data_age_hours'         => $dataAge,
                'is_stale'               => $isStale
            ];

        } catch (\Exception $e) {
            $logFallbackEvents = config('subscription.fallback.log_fallback_events', true);

            if ($logFallbackEvents) {
                Log::error('Failed to get subscription status from local database: ' . $e->getMessage(), [
                    'user_uuid' => $user->uuid,
                    'stripe_id' => $user->stripe_id,
                    'error'     => $e->getMessage()
                ]);
            }

            // Ostateczny fallback - sprawdź tylko lokalne trial
            $isOnActiveTrial = $user->trial_ends_at && $user->trial_ends_at->isFuture();

            return [
                'status'           => $isOnActiveTrial ? 'trial' : 'unknown',
                'has_subscription' => $isOnActiveTrial,
                'error'            => 'Failed to retrieve subscription data from both Stripe and local database',
                'on_trial'         => $isOnActiveTrial,
                'trial_ends_at'    => $user->trial_ends_at,
                'source'           => 'error_fallback'
            ];
        }
    }

    /**
     * Map Stripe status to our internal status
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match($stripeStatus) {
            'active'             => 'active',
            'trialing'           => 'trial',
            'past_due'           => 'past_due',
            'canceled'           => 'canceled',
            'unpaid'             => 'canceled',
            'incomplete'         => 'incomplete',
            'incomplete_expired' => 'expired',
            default              => 'unknown'
        };
    }

    /**
     * Start trial
     */
    public function startTrial(User $user): bool
    {
        try {
            // Check if user already has an active subscription status
            $subscriptionStatus = $this->getSubscriptionStatus($user);
            if ($subscriptionStatus['status'] === 'active') {
                throw new \InvalidArgumentException('You already have an active subscription');
            }

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
            // Get default currency if not specified
            $currency = $data['currency'] ?? $this->getDefaultCurrency();

            // Validate plan if provided
            if (isset($data['plan'])) {
                $plans    = config('subscription.plans');
                $planName = $data['plan'];

                if (!isset($plans[$planName])) {
                    throw new \InvalidArgumentException('Invalid plan selected');
                }

                $plan = $plans[$planName];

                // Validate currency for the plan
                if (!isset($plan['currencies'][$currency])) {
                    throw new \InvalidArgumentException("Currency '{$currency}' is not supported for plan '{$planName}'");
                }

                $planCurrency = $plan['currencies'][$currency];
            }

            // Check if user is subscribing to a plan or starting a trial
            $mode = $data['mode'] ?? 'subscription';

            // Create success and cancel URLs
            $successUrl = $data['success_url'] ?? config('app.frontend_url') . '/subscription/success';
            $cancelUrl  = $data['cancel_url']  ?? config('app.frontend_url') . '/subscription/cancel';

            // Build checkout session parameters
            $checkoutParams = [
                'customer'    => $this->getStripeCustomerId($user),
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'mode'        => $mode,
                'currency'    => strtolower($currency),
            ];

            // For subscription mode, add line items based on the selected plan
            if ($mode === 'subscription' && isset($data['plan'])) {
                $checkoutParams['line_items'] = [[
                    'price'    => $planCurrency['stripe_id'],
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
                    'plan'         => $data['plan'] ?? null,
                    'currency'     => $currency,
                    'mode'         => $mode,
                    'session_uuid' => $session->uuid,
                ])
                ->log('created checkout session');

            return [
                'url'          => $session->url,
                'session_uuid' => $session->uuid,
                'currency'     => $currency,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Checkout session: ' . $e->getMessage(), [
                'user_uuid' => $user->uuid,
                'trace'     => $e->getTraceAsString(),
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
                'customer'   => $user->stripe_id,
                'return_url' => $returnUrl,
            ]);

            // Log activity
            activity()
                ->causedBy($user)
                ->withProperties(['session_uuid' => $session->uuid])
                ->log('accessed billing portal');

            return [
                'url'          => $session->url,
                'session_uuid' => $session->uuid,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create Billing Portal session: ' . $e->getMessage(), [
                'user_uuid' => $user->uuid,
                'trace'     => $e->getTraceAsString(),
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
            'name'  => $user->name,
        ]);

        return $customer->id;
    }

    /**
     * Get default currency from configuration
     */
    protected function getDefaultCurrency(): string
    {
        $currencies = config('subscription.currencies', []);

        foreach ($currencies as $code => $config) {
            if ($config['default'] ?? false) {
                return $code;
            }
        }

        // Fallback to first currency if no default is set
        return array_key_first($currencies) ?? 'PLN';
    }

    /**
     * Get available currencies
     */
    public function getAvailableCurrencies(): array
    {
        return config('subscription.currencies', []);
    }

    /**
     * Get available plans with currency information
     */
    public function getAvailablePlans(?string $currency = null): array
    {
        $plans    = config('subscription.plans', []);
        $currency = $currency ?? $this->getDefaultCurrency();

        $result = [];
        foreach ($plans as $planKey => $plan) {
            if (isset($plan['currencies'][$currency])) {
                $planData = [
                    'name'      => $plan['name'],
                    'interval'  => $plan['interval'],
                    'currency'  => $currency,
                    'stripe_id' => $plan['currencies'][$currency]['stripe_id'],
                ];

                // Get price from Stripe API or use fallback
                if (config('subscription.pricing.use_stripe_api', true)) {
                    $stripePrice        = $this->getStripePriceInfo($plan['currencies'][$currency]['stripe_id']);
                    $planData['price']  = $stripePrice['amount'] ?? $plan['currencies'][$currency]['fallback_price'];
                    $planData['source'] = $stripePrice['source'] ?? 'fallback';
                } else {
                    $planData['price']  = $plan['currencies'][$currency]['fallback_price'];
                    $planData['source'] = 'config';
                }

                $result[$planKey] = $planData;
            }
        }

        return $result;
    }

    /**
     * Get price information from Stripe API with caching
     */
    private function getStripePriceInfo(string $priceId): array
    {
        $cacheKey  = "stripe_price_{$priceId}";
        $cacheTime = config('subscription.pricing.cache_duration', 3600);

        return cache()->remember($cacheKey, $cacheTime, function () use ($priceId) {
            try {
                $stripe = new \Stripe\StripeClient(config('cashier.secret'));
                $price  = $stripe->prices->retrieve($priceId);

                return [
                    'amount'   => $price->unit_amount / 100,
                    'currency' => strtoupper($price->currency),
                    'source'   => 'stripe'
                ];
            } catch (\Exception $e) {
                if (config('subscription.pricing.log_pricing_fallbacks', true)) {
                    Log::warning("Failed to retrieve Stripe price {$priceId}: " . $e->getMessage());
                }
                return [
                    'source' => 'fallback'
                ];
            }
        });
    }

    /**
     * Validate currency code
     */
    protected function validateCurrency(string $currency): bool
    {
        $supportedCurrencies = array_keys(config('subscription.currencies', []));
        return in_array($currency, $supportedCurrencies);
    }

    /**
     * Get currency information
     */
    public function getCurrencyInfo(string $currency): ?array
    {
        $currencies = config('subscription.currencies', []);
        return $currencies[$currency] ?? null;
    }
}
