<?php

namespace App\Console\Commands\Subscription;

use App\Console\Commands\BaseCommand;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */    
    protected $signature = 'subscription:sync
                            {--days=2 : Number of days to look back for changes}
                            {--user= : Specific user ID to sync subscriptions for}
                            {--dry-run : Show what would be done without making changes}
                            {--sync-roles : Also sync user roles based on subscription status}
                            {--batch-size=50 : Number of users to process in each batch}';

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
        
        try {
            if ($userId = $this->option('user')) {
                return $this->syncSpecificUser($userId);
            }
            
            return $this->syncAllSubscriptions();
            
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
     * Sync subscriptions for a specific user
     */
    private function syncSpecificUser(string $userId): int
    {
        $user = User::findOrFail($userId);
        $this->info("Syncing subscriptions for user: {$user->id} ({$user->email})");
        
        $this->syncUserSubscriptions($user);
        $this->info("Completed sync for user ID: {$userId}");
        
        return self::SUCCESS;
    }

    /**
     * Sync subscriptions for all users who have Stripe IDs
     */
    private function syncAllSubscriptions(): int
    {
        $days = $this->option('days');
        $batchSize = $this->option('batch-size');
        
        // Get users with Stripe IDs, optionally filtering by recent activity
        $query = User::whereNotNull('stripe_id');
        
        if ($days > 0) {
            $since = Carbon::now()->subDays($days);
            $query->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)
                  ->orWhere('last_subscription_sync', '>=', $since)
                  ->orWhereNull('last_subscription_sync');
            });
        }
        
        $totalUsers = $query->count();
        
        if ($totalUsers === 0) {
            $this->info('No users found with Stripe IDs to sync.');
            return self::SUCCESS;
        }
        
        $this->info("Found {$totalUsers} users to sync.");
        
        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();
        
        $processedCount = 0;
        $errorCount = 0;
        
        $query->chunk($batchSize, function ($users) use ($progressBar, &$processedCount, &$errorCount) {
            foreach ($users as $user) {
                try {
                    $this->syncUserSubscriptions($user);
                    $processedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('Error syncing user subscriptions', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $progressBar->advance();
            }
        });
        
        $progressBar->finish();
        $this->newLine();
        
        $this->info("Synchronization completed.");
        $this->line("  Processed: {$processedCount} users");
        if ($errorCount > 0) {
            $this->line("  Errors: {$errorCount} users");
        }
        
        return self::SUCCESS;
    }

    /**
     * Sync subscriptions for a specific user
     */
    private function syncUserSubscriptions(User $user): void
    {
        if ($this->output->isVerbose()) {
            $this->info("Syncing subscriptions for user: {$user->id} ({$user->email})");
        }
        
        // Skip if user doesn't have a Stripe ID
        if (!$user->stripe_id) {
            if ($this->output->isVerbose()) {
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
            $this->updateSyncTimestamp($user);
            
            // Sync user role if requested
            if ($this->option('sync-roles')) {
                $this->syncUserRole($user);
            }
            
        } catch (\Exception $e) {
            if ($this->output->isVerbose()) {
                $this->error("  Error syncing user {$user->id}: {$e->getMessage()}");
            }
            throw $e;
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
        $subscription->type = 'default';
        $subscription->stripe_status = $stripeSubscription->status;
        $subscription->stripe_price = $stripeSubscription->items->data[0]->price->id ?? null;
        $subscription->quantity = $stripeSubscription->items->data[0]->quantity ?? 1;
        $subscription->trial_ends_at = $stripeSubscription->trial_end ? 
            Carbon::createFromTimestamp($stripeSubscription->trial_end) : null;
        $subscription->ends_at = $stripeSubscription->cancel_at ? 
            Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null;
        
        if ($isDryRun) {
            $action = $isNew ? "Would create" : "Would update";
            if ($this->output->isVerbose() || $isNew) {
                $this->line("  {$action} subscription {$stripeSubscription->id} ({$stripeSubscription->status})");
            }
        } else {
            // Save the subscription record
            $subscription->save();
            
            // Process subscription items
            foreach ($stripeSubscription->items->data as $item) {
                $this->processSubscriptionItem($subscription, $item);
            }
            
            if ($this->output->isVerbose()) {
                $action = $isNew ? "Created" : "Updated";
                $this->line("  {$action} subscription {$stripeSubscription->id} ({$stripeSubscription->status})");
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
            if ($this->output->isVerbose() || $isNew) {
                $action = $isNew ? "Would create" : "Would update";
                $this->line("    {$action} subscription item {$item->id}");
            }
        } else {
            $subscriptionItem->save();
            
            if ($this->output->isVerbose()) {
                $action = $isNew ? "Created" : "Updated";
                $this->line("    {$action} subscription item {$item->id}");
            }
        }
    }

    /**
     * Sync payment method information
     */
    private function syncPaymentMethod(User $user): void
    {
        try {
            $isDryRun = $this->option('dry-run');
            
            // Get default payment method
            $customer = \Stripe\Customer::retrieve($user->stripe_id);
            
            if ($customer->invoice_settings->default_payment_method) {
                $paymentMethod = \Stripe\PaymentMethod::retrieve(
                    $customer->invoice_settings->default_payment_method
                );
                
                // Store basic payment method info (you might want to extend this)
                if (!$isDryRun) {
                    // You could store payment method details in a separate table
                    // For now, just log that we found it
                    if ($this->output->isVerbose()) {
                        $this->line("  Found payment method: {$paymentMethod->type}");
                    }
                }
            }
        } catch (\Exception $e) {
            // Payment method sync is not critical, just log and continue
            if ($this->output->isVerbose()) {
                $this->line("  Could not sync payment method: {$e->getMessage()}");
            }
        }
    }

    /**
     * Update the last sync timestamp
     */
    private function updateSyncTimestamp(User $user): void
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            if ($this->output->isVerbose()) {
                $this->line("  Would update last sync timestamp for user {$user->id}");
            }
        } else {
            $user->last_subscription_sync = now();
            $user->save();
            
            if ($this->output->isVerbose()) {
                $this->line("  Updated last sync timestamp for user {$user->id}");
            }
        }
    }

    /**
     * Sync user role based on subscription status
     */
    private function syncUserRole(User $user): void
    {
        $isDryRun = $this->option('dry-run');
        
        try {
            $appropriateRole = $this->determineUserRole($user);
            $currentRoles = $user->roles->pluck('name')->toArray();
            
            // Preserve admin role
            $newRoles = $user->hasRole('admin') ? ['admin', $appropriateRole] : [$appropriateRole];
            
            if (array_diff($newRoles, $currentRoles) || array_diff($currentRoles, $newRoles)) {
                if ($isDryRun) {
                    $this->line("  Would sync role to: {$appropriateRole}");
                } else {
                    $user->syncRoles($newRoles);
                    $this->line("  Synced role to: {$appropriateRole}");
                }
            }
        } catch (\Exception $e) {
            if ($this->output->isVerbose()) {
                $this->line("  Could not sync user role: {$e->getMessage()}");
            }
        }
    }

    /**
     * Determine appropriate role based on subscription status
     */
    private function determineUserRole(User $user): string
    {
        // Check for active subscription
        $activeSubscription = $user->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing'])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($activeSubscription) {
            return $activeSubscription->stripe_status === 'trialing' ? 'trial' : 'premium';
        }

        // Check for active trial
        if ($user->trial_ends_at && $user->trial_ends_at->isFuture()) {
            return 'trial';
        }

        // Check for canceled subscription that hasn't ended yet
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
