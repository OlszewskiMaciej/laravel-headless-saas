<?php

namespace App\Modules\Subscription\Services;

use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Event;

class WebhookService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Handle invoice payment succeeded - subscription auto-renewal
     */
    public function handlePaymentSucceeded(array|Event $payload): bool
    {
        try {
            // For testing environment, accept direct Event object
            $event = is_array($payload) ? Event::constructFrom($payload) : $payload;
            
            $invoice = $event->data->object;

            // Skip if this isn't a subscription invoice
            if (!isset($invoice->subscription)) {
                return true;
            }

            Log::info('Subscription payment succeeded', [
                'customer' => $invoice->customer,
                'subscription' => $invoice->subscription
            ]);

            // Find the relevant user
            $user = User::where('stripe_id', $invoice->customer)->first();
            
            if (!$user) {
                Log::warning('User not found for subscription payment', ['customer' => $invoice->customer]);
                return true;
            }

            // Ensure the user has the premium role, preserving admin role if they have it
            if (!$user->hasRole('premium')) {
                $roles = $user->hasRole('admin') ? ['admin', 'premium'] : ['premium'];
                $this->userRepository->syncRoles($user, $roles);
                
                Log::info('User role updated after successful payment', [
                    'user_id' => $user->id, 
                    'roles' => $roles
                ]);
            }

            // Log the renewal activity
            activity()
                ->causedBy($user)
                ->withProperties([
                    'subscription_id' => $invoice->subscription,
                    'invoice_id' => $invoice->id,
                ])
                ->log('subscription renewed automatically');

            return true;
        } catch (\Exception $e) {
            Log::error('Error handling payment succeeded webhook: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Handle invoice payment failed
     */
    public function handlePaymentFailed(array|Event $payload): bool
    {
        try {
            // For testing environment, accept direct Event object
            $event = is_array($payload) ? Event::constructFrom($payload) : $payload;
            
            $invoice = $event->data->object;

            // Skip if this isn't a subscription invoice
            if (!isset($invoice->subscription)) {
                return true;
            }

            Log::warning('Subscription payment failed', [
                'customer' => $invoice->customer,
                'subscription' => $invoice->subscription,
                'attempt_count' => $invoice->attempt_count ?? 1
            ]);

            $user = User::where('stripe_id', $invoice->customer)->first();
            
            if (!$user) {
                Log::warning('User not found for failed payment', ['customer' => $invoice->customer]);
                return true;
            }

            // Log the failed renewal
            activity()
                ->causedBy($user)
                ->withProperties([
                    'subscription_id' => $invoice->subscription,
                    'invoice_id' => $invoice->id,
                    'attempt_count' => $invoice->attempt_count ?? 1
                ])
                ->log('subscription payment failed');

            return true;
        } catch (\Exception $e) {
            Log::error('Error handling payment failed webhook: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
