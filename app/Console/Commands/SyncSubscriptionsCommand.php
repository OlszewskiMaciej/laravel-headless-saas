<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */    
    protected $signature = 'subscriptions:sync
                            {--days=2 : Number of days to look back for changes}
                            {--user= : Specific user ID to sync subscriptions for}
                            {--dry-run : Show what would be done without making changes}
                            {--sync-roles : Also sync user roles based on subscription status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Stripe subscriptions data with local database';

    /**
     * Execute the console command.
     */    
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        
        $this->info('Starting subscription synchronization...' . ($isDryRun ? ' (DRY RUN)' : ''));
        
        $days = $this->option('days');
        $userId = $this->option('user');
        
        try {
            if ($userId) {
                $user = User::findOrFail($userId);
                $this->syncUserSubscriptions($user);
                $this->info("Completed sync for user ID: {$userId}");
                return self::SUCCESS;
            }
            
            $this->syncAllSubscriptions($days);
            $this->info('Subscription synchronization completed successfully.');
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error syncing subscriptions: ' . $e->getMessage());
            Log::error('Subscription sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Sync subscriptions for all users who have Stripe IDs
     */
    private function syncAllSubscriptions(int $days): void
    {
        $users = User::whereNotNull('stripe_id')
                    ->where(function($query) use ($days) {
                        // Include users who haven't been synced recently
                        $query->whereNull('last_subscription_sync')
                        // Or users with sync older than specified days
                        ->orWhere('last_subscription_sync', '<=', Carbon::now()->subDays($days))
                        // Include users with recently updated subscriptions
                        ->orWhereHas('subscriptions', function($subQuery) use ($days) {
                            $subQuery->where('updated_at', '>=', Carbon::now()->subDays($days));
                        })
                        // Or users with payment method data
                        ->orWhereNotNull('pm_type')
                        // Or users with never synced data
                        ->orDoesntHave('subscriptions');
                    })
                    ->get();
                      if ($this->getOutput()->isVerbose()) {
            $this->info("Found {$users->count()} users to sync");
        }
                    
        $this->output->progressStart($users->count());
        
        foreach ($users as $user) {
            $this->syncUserSubscriptions($user);
            $this->output->progressAdvance();
        }
        
        $this->output->progressFinish();
    }

    /**
     * Sync subscriptions for a specific user
     */
    private function syncUserSubscriptions(User $user): void
    {        if ($this->getOutput()->isVerbose()) {
            $this->info("Syncing subscriptions for user: {$user->id} ({$user->email})");
        }
        
        // Skip if user doesn't have a Stripe ID
        if (!$user->stripe_id) {
            if ($this->getOutput()->isVerbose()) {
                $this->line("  Skipping user {$user->id}: No Stripe ID");
            }
            return;
        }
          try {            
            // Get active subscriptions from Stripe
            $stripeSubscriptions = \Stripe\Subscription::all([
                'customer' => $user->stripe_id,
                'limit' => 100,
                'expand' => ['data.items.data.price'],
            ]);
                  
            // Process each subscription
            foreach ($stripeSubscriptions->data as $stripeSubscription) {
                $this->processSubscription($user, $stripeSubscription);
            }
            
            // Store payment method details if available
            $this->syncPaymentMethod($user);
              
            // Update the last subscription sync timestamp
            $isDryRun = $this->option('dry-run');
            if ($isDryRun) {
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  Would update last sync timestamp for user {$user->id}");
                }
            } else {
                $user->last_subscription_sync = now();
                $user->save();
                
                if ($this->getOutput()->isVerbose()) {
                    $this->line("  Updated last sync timestamp for user {$user->id}");
                }
            }
            
            // Sync user role if requested
            if ($this->option('sync-roles')) {
                $this->syncUserRole($user, $isDryRun);
            }
        } catch (\Exception $e) {
            if ($this->getOutput()->isVerbose()) {
                $this->error("  Error syncing user {$user->id}: {$e->getMessage()}");
            }
            Log::error('Error syncing user subscriptions', [
                'user_id' => $user->id,
                'stripe_id' => $user->stripe_id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Process a single subscription and its items
     */    
    private function processSubscription(User $user, \Stripe\Subscription $stripeSubscription): void
    {
        $isDryRun = $this->option('dry-run');
        
        // Find or create local subscription record
        $subscription = Subscription::firstOrNew([
            'stripe_id' => $stripeSubscription->id,
        ]);
        
        $isNew = !$subscription->exists;
          
        // Update subscription attributes
        $subscription->user_id = $user->id;
        $subscription->type = 'default'; // Or determine type based on products
        $subscription->stripe_status = $stripeSubscription->status;
        $subscription->stripe_price = $stripeSubscription->items->data[0]->price->id ?? null;
        $subscription->quantity = $stripeSubscription->items->data[0]->quantity ?? 1;
        $subscription->trial_ends_at = $stripeSubscription->trial_end ? 
            Carbon::createFromTimestamp($stripeSubscription->trial_end) : null;
        $subscription->ends_at = $stripeSubscription->cancel_at ? 
            Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null;
          if ($isDryRun) {
            if ($this->getOutput()->isVerbose() || $isNew) {
                $action = $isNew ? "Would create" : "Would update";
                $this->line("  {$action} subscription {$stripeSubscription->id} ({$stripeSubscription->status})");
            }
        } else {
            // Save the subscription record
            $subscription->save();
            
            // Process subscription items
            foreach ($stripeSubscription->items->data as $item) {
                $this->processSubscriptionItem($subscription, $item);
            }
            
            if ($this->getOutput()->isVerbose()) {
                $this->line("  Synced subscription {$stripeSubscription->id} ({$stripeSubscription->status})");
            }
        }
    }
    
    /**
     * Process a single subscription item
     */    
    private function processSubscriptionItem(Subscription $subscription, \Stripe\SubscriptionItem $item): void
    {
        $isDryRun = $this->option('dry-run');
        
        // Find or create subscription item
        $subscriptionItem = SubscriptionItem::firstOrNew([
            'stripe_id' => $item->id,
        ]);
        
        $isNew = !$subscriptionItem->exists;        
        
        // Update subscription item attributes
        $subscriptionItem->subscription_id = $subscription->id;
        $subscriptionItem->stripe_product = $item->price->product ?? null;
        $subscriptionItem->stripe_price = $item->price->id;
        $subscriptionItem->quantity = $item->quantity;
          if ($isDryRun) {
            if ($this->getOutput()->isVerbose() || $isNew) {
                $action = $isNew ? "Would create" : "Would update";
                $this->line("    {$action} subscription item {$item->id} for price {$item->price->id}");
            }
        } else {
            // Save the subscription item
            $subscriptionItem->save();
        }
    }
    
    /**
     * Synchronize payment method details for a user
     */    
    private function syncPaymentMethod(User $user): void
    {
        $isDryRun = $this->option('dry-run');
        
        // Skip if the user already has payment info
        if ($user->pm_type && $user->pm_last_four) {
            return;
        }
        
        try {
            // Get default payment method
            $paymentMethod = $user->defaultPaymentMethod();
            
            if ($paymentMethod) {
                $pmType = $paymentMethod->type;
                $pmLastFour = $paymentMethod->card->last4 ?? null;
                  if ($isDryRun) {
                    if ($this->getOutput()->isVerbose()) {
                        $this->line("  Would update payment method: {$pmType} (**** {$pmLastFour})");
                    }
                } else {
                    $user->pm_type = $pmType;
                    $user->pm_last_four = $pmLastFour;
                    $user->save();
                    
                    if ($this->getOutput()->isVerbose()) {
                        $this->line("  Updated payment method: {$pmType} (**** {$pmLastFour})");
                    }
                }
            }        } catch (\Exception $e) {
            if ($this->getOutput()->isVerbose()) {
                $this->line("  Could not retrieve payment method: {$e->getMessage()}");
            }
        }
    }
    
    /**
     * Sync user role based on subscription status
     */
    private function syncUserRole(User $user, bool $isDryRun): void
    {
        $currentRoles = $user->roles->pluck('name')->toArray();
        
        // Skip admin users - don't change their roles
        if (in_array('admin', $currentRoles)) {
            if ($this->getOutput()->isVerbose()) {
                $this->line("  Skipping admin user");
            }
            return;
        }
        
        $expectedRole = $this->determineExpectedRole($user);
        
        // Skip if user already has the correct role
        if (in_array($expectedRole, $currentRoles) && count($currentRoles) === 1) {
            if ($this->getOutput()->isVerbose()) {
                $this->line("  User already has correct role: {$expectedRole}");
            }
            return;
        }

        if ($isDryRun) {
            $this->line("  Would change role from [" . implode(', ', $currentRoles) . "] to [{$expectedRole}] for user {$user->email}");
        } else {
            // Remove all current roles and assign the correct one
            $user->syncRoles([$expectedRole]);
            
            if ($this->getOutput()->isVerbose()) {
                $this->line("  Updated role from [" . implode(', ', $currentRoles) . "] to [{$expectedRole}] for user {$user->email}");
            }
            
            Log::info('User role synchronized during subscription sync', [
                'user_id' => $user->id,
                'email' => $user->email,
                'old_roles' => $currentRoles,
                'new_role' => $expectedRole
            ]);
        }
    }

    /**
     * Determine the expected role for a user based on their subscription status
     */
    private function determineExpectedRole(User $user): string
    {
        // Check if user has an active subscription
        $activeSubscription = $user->subscriptions()
            ->where('stripe_status', 'active')
            ->first();

        if ($activeSubscription) {
            return 'premium';
        }

        // Check if user is on trial
        if ($user->onTrial()) {
            return 'trial';
        }

        // Check if user has any subscription with trialing status
        $trialSubscription = $user->subscriptions()
            ->where('stripe_status', 'trialing')
            ->first();

        if ($trialSubscription) {
            return 'trial';
        }

        // Check if user has canceled subscription but still within grace period
        $canceledSubscription = $user->subscriptions()
            ->where('stripe_status', 'canceled')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', Carbon::now())
            ->first();

        if ($canceledSubscription) {
            return 'premium';
        }

        // Default to free role
        return 'free';
    }
}
