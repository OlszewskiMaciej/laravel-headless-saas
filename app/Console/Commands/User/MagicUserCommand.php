<?php

namespace App\Console\Commands\User;

use App\Console\Commands\BaseCommand;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MagicUserCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:magic 
                            {action : The magic action to perform}
                            {user? : The user email or UUID}
                            {--force : Force the action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform magic user operations - reset passwords, toggle status, impersonate, etc.';

    /**
     * Available magic actions
     */
    private array $actions = [
        'verify-email' => 'Mark user email as verified',
        'unverify-email' => 'Mark user email as unverified',
        'login-token' => 'Generate a temporary login token',
        'clear-sessions' => 'Clear all user sessions',
        'emergency-admin' => 'Create emergency admin user',
        'user-info' => 'Show comprehensive user information',
        'bulk-operation' => 'Perform bulk operations on users',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        
        if (!array_key_exists($action, $this->actions)) {
            $this->failure("Invalid action: {$action}");
            $this->line('Available actions:');
            foreach ($this->actions as $key => $description) {
                $this->line("  {$key}: {$description}");
            }
            return self::FAILURE;
        }

        return $this->performAction($action);
    }

    /**
     * Perform the requested action
     */
    private function performAction(string $action): int
    {
        switch ($action) {
            case 'verify-email':
                return $this->verifyEmail();
            case 'unverify-email':
                return $this->unverifyEmail();
            case 'login-token':
                return $this->generateLoginToken();
            case 'clear-sessions':
                return $this->clearSessions();
            case 'emergency-admin':
                return $this->createEmergencyAdmin();
            case 'user-info':
                return $this->showUserInfo();
            case 'bulk-operation':
                return $this->bulkOperation();
            default:
                $this->failure("Action not implemented: {$action}");
                return self::FAILURE;
        }
    }

    /**
     * Verify user email
     */
    private function verifyEmail(): int
    {
        $user = $this->findUser();
        if (!$user) return self::FAILURE;

        if ($user->email_verified_at) {
            $this->warning("User email is already verified.");
            return self::SUCCESS;
        }

        $user->update(['email_verified_at' => now()]);
        $this->success("Email verified for {$user->name}");

        return self::SUCCESS;
    }

    /**
     * Unverify user email
     */
    private function unverifyEmail(): int
    {
        $user = $this->findUser();
        if (!$user) return self::FAILURE;

        if (!$user->email_verified_at) {
            $this->warning("User email is already unverified.");
            return self::SUCCESS;
        }

        $user->update(['email_verified_at' => null]);
        $this->success("Email unverified for {$user->name}");

        return self::SUCCESS;
    }

    /**
     * Generate login token
     */
    private function generateLoginToken(): int
    {
        $user = $this->findUser();
        if (!$user) return self::FAILURE;

        $token = $user->createToken('magic-login', ['*'], now()->addHours(1));
        
        $this->success("Login token generated for {$user->name}");
        $this->line("Token: {$token->plainTextToken}");
        $this->warning("This token expires in 1 hour!");

        return self::SUCCESS;
    }

    /**
     * Clear user sessions
     */
    private function clearSessions(): int
    {
        $user = $this->findUser();
        if (!$user) return self::FAILURE;

        $user->tokens()->delete();
        $this->success("All sessions cleared for {$user->name}");

        return self::SUCCESS;
    }

    /**
     * Create emergency admin user
     */
    private function createEmergencyAdmin(): int
    {
        $email = 'emergency@admin.local';
        $password = $this->generateTempPassword();

        if (User::where('email', $email)->exists()) {
            $this->failure("Emergency admin user already exists!");
            return self::FAILURE;
        }

        $user = User::create([
            'name' => 'Emergency Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'trial_ends_at' => Carbon::create(2037, 1, 1, 0, 0, 0),
        ]);

        $user->assignRole('admin');

        $this->success("Emergency admin user created!");
        $this->line("Email: {$email}");
        $this->line("Password: {$password}");
        $this->warning("Delete this user after resolving your emergency!");

        return self::SUCCESS;
    }

    /**
     * Show comprehensive user info
     */
    private function showUserInfo(): int
    {
        $user = $this->findUser();
        if (!$user) return self::FAILURE;

        $this->line("=== User Information ===");
        $this->line("UUID: {$user->uuid}");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line("Created: {$user->created_at}");
        $this->line("Updated: {$user->updated_at}");
        $this->line("Email Verified: " . ($user->email_verified_at ? 'Yes' : 'No'));
        $this->line("Trial Ends: " . ($user->trial_ends_at ? $user->trial_ends_at : 'No trial'));
        
        $roles = $user->roles->pluck('name')->toArray();
        $this->line("Roles: " . (empty($roles) ? 'None' : implode(', ', $roles)));
        
        $this->line("Active Tokens: " . $user->tokens()->count());
        
        if ($user->subscriptions) {
            $this->line("Subscriptions: " . $user->subscriptions()->count());
        }

        return self::SUCCESS;
    }

    /**
     * Bulk operations
     */
    private function bulkOperation(): int
    {
        $operation = $this->choice(
            'What bulk operation would you like to perform?',
            [
                'Verify all unverified emails',
                'Extend all trials by X days',
                'Assign role to all users',
                'Clear all user sessions',
                'List users by role',
                'Cancel'
            ]
        );

        switch ($operation) {
            case 'Verify all unverified emails':
                return $this->bulkVerifyEmails();
            case 'Extend all trials by X days':
                return $this->bulkExtendTrials();
            case 'Assign role to all users':
                return $this->bulkAssignRole();
            case 'Clear all user sessions':
                return $this->bulkClearSessions();
            case 'List users by role':
                return $this->listUsersByRole();
            default:
                $this->warning("Operation cancelled.");
                return self::SUCCESS;
        }
    }

    /**
     * Bulk verify emails
     */
    private function bulkVerifyEmails(): int
    {
        $count = User::whereNull('email_verified_at')->count();
        
        if ($count === 0) {
            $this->success("All users already have verified emails.");
            return self::SUCCESS;
        }

        if ($this->confirm("Verify emails for {$count} users?")) {
            User::whereNull('email_verified_at')->update(['email_verified_at' => now()]);
            $this->success("Verified emails for {$count} users.");
        }

        return self::SUCCESS;
    }

    /**
     * Bulk extend trials
     */
    private function bulkExtendTrials(): int
    {
        $days = $this->ask('How many days to extend trials?', '30');
        $days = (int) $days; // Convert to integer
        $count = User::whereNotNull('trial_ends_at')->count();

        if ($count === 0) {
            $this->warning("No users have trials to extend.");
            return self::SUCCESS;
        }

        if ($this->confirm("Extend trials for {$count} users by {$days} days?")) {
            User::whereNotNull('trial_ends_at')
                ->update(['trial_ends_at' => now()->addDays($days)]);
            $this->success("Extended trials for {$count} users by {$days} days.");
        }

        return self::SUCCESS;
    }

    /**
     * Bulk assign role
     */
    private function bulkAssignRole(): int
    {
        $roleName = $this->ask('Which role to assign to all users?');
        $userCount = User::count();

        if ($this->confirm("Assign role '{$roleName}' to all {$userCount} users?")) {
            foreach (User::all() as $user) {
                $user->assignRole($roleName);
            }
            $this->success("Role '{$roleName}' assigned to all users.");
        }

        return self::SUCCESS;
    }

    /**
     * Bulk clear sessions
     */
    private function bulkClearSessions(): int
    {
        if ($this->confirm("Clear all user sessions? This will log out all users.")) {
            DB::table('personal_access_tokens')->delete();
            $this->success("All user sessions cleared.");
        }

        return self::SUCCESS;
    }

    /**
     * List users by role
     */
    private function listUsersByRole(): int
    {
        $roleName = $this->ask('Which role to filter by?');
        $users = User::role($roleName)->get();

        if ($users->isEmpty()) {
            $this->warning("No users found with role '{$roleName}'.");
        } else {
            $this->success("Users with role '{$roleName}':");
            foreach ($users as $user) {
                $this->line("  - {$user->name} ({$user->email})");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Find user by email or UUID
     */
    private function findUser(): ?User
    {
        $userIdentifier = $this->argument('user');
        
        if (!$userIdentifier) {
            $userIdentifier = $this->ask('Enter user email or UUID:');
        }

        $user = User::where('email', $userIdentifier)
                   ->orWhere('uuid', $userIdentifier)
                   ->first();

        if (!$user) {
            $this->failure("User not found: {$userIdentifier}");
            return null;
        }

        return $user;
    }

    /**
     * Generate temporary password
     */
    private function generateTempPassword(): string
    {
        return 'temp_' . Str::random(8) . '!';
    }
}
