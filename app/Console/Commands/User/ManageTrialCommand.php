<?php

namespace App\Console\Commands\User;

use App\Console\Commands\BaseCommand;
use App\Models\User;
use Carbon\Carbon;

class ManageTrialCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:trial 
                            {user : The user email or UUID}
                            {--extend= : Extend trial by X days}
                            {--set= : Set trial end date (Y-m-d format)}
                            {--unlimited : Set unlimited trial}
                            {--remove : Remove trial access}
                            {--status : Show trial status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage user trial access - extend, set, make unlimited, or remove';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userIdentifier = $this->argument('user');
        
        // Find user by email or UUID
        $user = User::where('email', $userIdentifier)
                   ->orWhere('uuid', $userIdentifier)
                   ->first();

        if (!$user) {
            $this->failure("User not found: {$userIdentifier}");
            return self::FAILURE;
        }

        $this->line("Managing trial for: {$user->name} ({$user->email})");

        // Show status
        if ($this->option('status')) {
            return $this->showTrialStatus($user);
        }

        // Remove trial
        if ($this->option('remove')) {
            return $this->removeTrial($user);
        }

        // Set unlimited trial
        if ($this->option('unlimited')) {
            return $this->setUnlimitedTrial($user);
        }

        // Extend trial
        if ($extendDays = $this->option('extend')) {
            return $this->extendTrial($user, (int) $extendDays);
        }

        // Set specific trial end date
        if ($setDate = $this->option('set')) {
            return $this->setTrialEndDate($user, $setDate);
        }

        // Interactive mode
        return $this->interactiveMode($user);
    }

    /**
     * Show trial status
     */
    private function showTrialStatus(User $user): int
    {
        $this->line("Trial Status for {$user->name}:");
        
        if (!$user->trial_ends_at) {
            $this->warning("No trial configured");
            return self::SUCCESS;
        }

        $trialEnd = $user->trial_ends_at;
        $now = Carbon::now();
        
        $this->line("Trial ends: {$trialEnd->format('Y-m-d H:i:s')}");
        
        if ($trialEnd->year > 2090) {
            $this->success("Status: Unlimited trial");
        } elseif ($trialEnd->isFuture()) {
            $daysLeft = $now->diffInDays($trialEnd);
            $this->success("Status: Active ({$daysLeft} days remaining)");
        } else {
            $daysAgo = $trialEnd->diffInDays($now);
            $this->failure("Status: Expired ({$daysAgo} days ago)");
        }

        return self::SUCCESS;
    }

    /**
     * Remove trial access
     */
    private function removeTrial(User $user): int
    {
        if ($this->confirm("Are you sure you want to remove trial access for {$user->name}?")) {
            $user->update(['trial_ends_at' => null]);
            $this->success("Trial access removed.");
        } else {
            $this->warning("Operation cancelled.");
        }

        return self::SUCCESS;
    }

    /**
     * Set unlimited trial
     */
    private function setUnlimitedTrial(User $user): int
    {
        $user->update(['trial_ends_at' => Carbon::create(2037, 1, 1, 0, 0, 0)]);
        $this->success("Unlimited trial access granted!");
        
        return self::SUCCESS;
    }

    /**
     * Extend trial by days
     */
    private function extendTrial(User $user, int $days): int
    {
        $currentTrialEnd = $user->trial_ends_at;
        $baseDate = $currentTrialEnd && $currentTrialEnd->isFuture() ? $currentTrialEnd : Carbon::now();
        
        $newTrialEnd = $baseDate->addDays($days);
        $user->update(['trial_ends_at' => $newTrialEnd]);
        
        $this->success("Trial extended by {$days} days.");
        $this->line("New trial end date: {$newTrialEnd->format('Y-m-d H:i:s')}");
        
        return self::SUCCESS;
    }

    /**
     * Set specific trial end date
     */
    private function setTrialEndDate(User $user, string $dateString): int
    {
        try {
            $date = Carbon::createFromFormat('Y-m-d', $dateString)->endOfDay();
        } catch (\Exception $e) {
            $this->failure("Invalid date format. Use Y-m-d format (e.g., 2024-12-31)");
            return self::FAILURE;
        }

        $user->update(['trial_ends_at' => $date]);
        $this->success("Trial end date set to: {$date->format('Y-m-d H:i:s')}");
        
        return self::SUCCESS;
    }

    /**
     * Interactive trial management
     */
    private function interactiveMode(User $user): int
    {
        // Show current status first
        $this->showTrialStatus($user);
        $this->line('');

        $action = $this->choice(
            'What would you like to do?',
            [
                'Extend trial by days',
                'Set specific end date',
                'Make unlimited trial',
                'Remove trial access',
                'Cancel'
            ],
            0
        );

        switch ($action) {
            case 'Extend trial by days':
                $days = $this->ask('How many days to extend?', '30');
                return $this->extendTrial($user, (int) $days);
                
            case 'Set specific end date':
                $date = $this->ask('Enter end date (Y-m-d format):');
                return $this->setTrialEndDate($user, $date);
                
            case 'Make unlimited trial':
                return $this->setUnlimitedTrial($user);
                
            case 'Remove trial access':
                return $this->removeTrial($user);
                
            default:
                $this->warning("Operation cancelled.");
                return self::SUCCESS;
        }
    }
}
