<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredTrials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:check-expired-trials {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expired trials and downgrade users to free if they don\'t have active paid subscriptions';

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UserRepositoryInterface $userRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired trials...');
        
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made.');
        }

        // Find users with expired trials
        $expiredTrialUsers = User::where('trial_ends_at', '<=', Carbon::now())
            ->whereNotNull('trial_ends_at')
            ->with('roles')
            ->get();

        if ($expiredTrialUsers->isEmpty()) {
            $this->info('No users with expired trials found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$expiredTrialUsers->count()} users with expired trials.");

        $downgradedCount = 0;
        $premiumRetainedCount = 0;
        $errorCount = 0;

        foreach ($expiredTrialUsers as $user) {
            try {
                $this->line("Processing user: {$user->name} ({$user->email})");
                
                // Check if user has active paid subscription
                $subscriptionStatus = $this->subscriptionService->getSubscriptionStatus($user);
                $hasActivePaidSubscription = $subscriptionStatus['status'] === 'active' && 
                                           $subscriptionStatus['has_subscription'] && 
                                           !$subscriptionStatus['on_trial'];

                if ($hasActivePaidSubscription) {
                    $this->line("  ✓ User has active paid subscription - keeping premium access");
                    $premiumRetainedCount++;
                    continue;
                }

                // Check if user still has trial or premium role
                if (!$user->hasRole('trial') && !$user->hasRole('premium')) {
                    $this->line("  - User already has appropriate role");
                    continue;
                }                // Downgrade user to free
                if (!$dryRun) {
                    $this->downgradeUserToFree($user);
                }
                
                $action = $dryRun ? 'Would downgrade' : 'Downgraded';
                $this->line("  ✓ {$action} user to free role and removed 'start trial' permission");
                $downgradedCount++;

            } catch (\Exception $e) {
                $this->error("  ✗ Error processing user {$user->email}: " . $e->getMessage());
                Log::error('Error processing expired trial user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorCount++;
            }
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $this->line("  Users processed: {$expiredTrialUsers->count()}");
        $this->line("  " . ($dryRun ? 'Would be downgraded' : 'Downgraded') . " to free: {$downgradedCount}");
        $this->line("  Retained premium (active subscriptions): {$premiumRetainedCount}");
        
        if ($errorCount > 0) {
            $this->line("  Errors: {$errorCount}");
        }

        if (!$dryRun && $downgradedCount > 0) {
            Log::info('Expired trials processed', [
                'total_processed' => $expiredTrialUsers->count(),
                'downgraded_count' => $downgradedCount,
                'premium_retained_count' => $premiumRetainedCount,
                'error_count' => $errorCount
            ]);
        }

        return Command::SUCCESS;
    }    
    
    /**
     * Downgrade user to free role while preserving admin role if present
     */
    private function downgradeUserToFree(User $user): void
    {
        // Store previous roles and permissions for logging
        $previousRoles = $user->roles->pluck('name')->toArray();
        
        // Preserve admin role if user has it
        $roles = $user->hasRole('admin') ? ['admin', 'free'] : ['free'];
        
        $this->userRepository->syncRoles($user, $roles);
        
        $user->save();

        // Log the activity
        activity()
            ->causedBy($user)
            ->withProperties([
                'previous_roles' => $previousRoles,
                'new_roles' => $roles,
                'reason' => 'trial_expired'
            ])
            ->log('trial expired and user downgraded to free');
    }
}
