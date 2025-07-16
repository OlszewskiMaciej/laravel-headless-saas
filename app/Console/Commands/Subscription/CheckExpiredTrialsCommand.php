<?php

namespace App\Console\Commands\Subscription;

use App\Console\Commands\BaseCommand;
use App\Models\User;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckExpiredTrialsCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:check-expired-trials 
                            {--dry-run : Show what would be done without making changes}
                            {--user= : Check specific user UUID only}';

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
        $specificUserUuid = $this->option('user');
        
        if ($dryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made.');
        }

        $expiredTrialUsers = $this->getExpiredTrialUsers($specificUserUuid);
        
        if ($expiredTrialUsers->isEmpty()) {
            $this->info('No users with expired trials found.');
            return self::SUCCESS;
        }

        $this->info("Found {$expiredTrialUsers->count()} users with expired trials.");
        
        $results = $this->processExpiredTrials($expiredTrialUsers, $dryRun);
        $this->displaySummary($results, $dryRun);
        
        return self::SUCCESS;
    }

    /**
     * Get users with expired trials
     */
    private function getExpiredTrialUsers(?string $specificUserUuid)
    {
        $query = User::where('trial_ends_at', '<=', Carbon::now())
            ->whereNotNull('trial_ends_at')
            ->with('roles');
            
        if ($specificUserUuid) {
            $query->where('uuid', $specificUserUuid);
        }
        
        return $query->get();
    }

    /**
     * Process expired trial users
     */
    private function processExpiredTrials($users, bool $dryRun): array
    {
        $results = [
            'downgraded' => 0,
            'premium_retained' => 0,
            'errors' => 0,
            'skipped' => 0
        ];

        foreach ($users as $user) {
            try {
                $this->line("Processing user: {$user->name} ({$user->email})");
                
                $result = $this->processUser($user, $dryRun);
                $results[$result]++;
                
            } catch (\Exception $e) {
                $this->handleUserError($user, $e);
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Process individual user
     */
    private function processUser(User $user, bool $dryRun): string
    {
        // Check if user has active paid subscription
        $subscriptionStatus = $this->subscriptionService->getSubscriptionStatus($user);
        $hasActivePaidSubscription = $subscriptionStatus['status'] === 'active' && 
                                   $subscriptionStatus['has_subscription'] && 
                                   !$subscriptionStatus['on_trial'];

        if ($hasActivePaidSubscription) {
            $this->line("  ✓ User has active paid subscription - keeping premium access");
            return 'premium_retained';
        }

        // Check if user still has trial or premium role
        if (!$user->hasRole('trial') && !$user->hasRole('premium')) {
            $this->line("  - User already has appropriate role");
            return 'skipped';
        }

        // Downgrade user to free
        if (!$dryRun) {
            $this->downgradeUserToFree($user);
        }
        
        $action = $dryRun ? 'Would downgrade' : 'Downgraded';
        $this->line("  ✓ {$action} user to free role");
        
        return 'downgraded';
    }

    /**
     * Downgrade user to free role while preserving admin role if present
     */
    private function downgradeUserToFree(User $user): void
    {
        $previousRoles = $user->roles->pluck('name')->toArray();
        $roles = $user->hasRole('admin') ? ['admin', 'free'] : ['free'];
        
        $this->userRepository->syncRoles($user, $roles);
        $user->save();

        $this->logTrialExpiration($user, $previousRoles, $roles);
    }

    /**
     * Log trial expiration activity
     */
    private function logTrialExpiration(User $user, array $previousRoles, array $newRoles): void
    {
        activity()
            ->causedBy($user)
            ->withProperties([
                'previous_roles' => $previousRoles,
                'new_roles' => $newRoles,
                'reason' => 'trial_expired',
                'expired_at' => $user->trial_ends_at
            ])
            ->log('trial expired and user downgraded to free');
    }

    /**
     * Handle user processing errors
     */
    private function handleUserError(User $user, \Exception $e): void
    {
        $this->error("  ✗ Error processing user {$user->email}: " . $e->getMessage());
        
        Log::error('Error processing expired trial user', [
            'user_uuid' => $user->uuid,
            'email' => $user->email,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    /**
     * Display processing summary
     */
    private function displaySummary(array $results, bool $dryRun): void
    {
        $this->newLine();
        $this->info('Summary:');
        
        $total = array_sum($results);
        $this->line("  Users processed: {$total}");
        $this->line("  " . ($dryRun ? 'Would be downgraded' : 'Downgraded') . " to free: {$results['downgraded']}");
        $this->line("  Retained premium (active subscriptions): {$results['premium_retained']}");
        $this->line("  Skipped (already appropriate role): {$results['skipped']}");
        
        if ($results['errors'] > 0) {
            $this->line("  Errors: {$results['errors']}");
        }

        if (!$dryRun && $results['downgraded'] > 0) {
            Log::info('Expired trials processed', $results);
        }
    }
}
