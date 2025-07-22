<?php

namespace App\Console\Commands\User;

use App\Console\Commands\BaseCommand;
use App\Models\User;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create 
                            {--name= : The name of the user}
                            {--email= : The email of the user}
                            {--password= : The password for the user}
                            {--role= : The role to assign to the user}
                            {--admin : Create an admin user}
                            {--trial-days= : Number of trial days (default: 30)}
                            {--unlimited-trial : Give unlimited trial access}
                            {--no-trial : Create user without trial}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user with optional role assignment and trial configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name     = $this->option('name') ?: $this->ask('What is the user\'s name?');
        $email    = $this->option('email') ?: $this->ask('What is the user\'s email?');
        $password = $this->option('password') ?: $this->secret('What is the user\'s password?');

        // Validate input
        $validator = Validator::make([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
        ], [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->failure($error);
            }
            return self::FAILURE;
        }

        // Handle trial configuration
        $trialEndsAt = null;
        if ($this->option('unlimited-trial')) {
            // Use a date far in the future but within MySQL's valid range
            $trialEndsAt = Carbon::create(2037, 1, 1, 0, 0, 0); // Effectively unlimited
        } elseif (!$this->option('no-trial')) {
            $trialDays   = (int) ($this->option('trial-days') ?: 30);
            $trialEndsAt = Carbon::now()->addDays($trialDays);
        }

        // Create user
        $user = User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make($password),
            'trial_ends_at'     => $trialEndsAt,
            'email_verified_at' => Carbon::now(),
        ]);

        $this->success('User created successfully!');
        $this->line("UUID: {$user->uuid}");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");

        if ($trialEndsAt) {
            if ($this->option('unlimited-trial')) {
                $this->line('Trial: Unlimited');
            } else {
                $this->line("Trial ends: {$trialEndsAt->format('Y-m-d H:i:s')}");
            }
        } else {
            $this->line('Trial: None');
        }

        // Handle role assignment
        if ($this->option('admin')) {
            $this->assignRole($user, 'admin');
        } elseif ($role = $this->option('role')) {
            $this->assignRole($user, $role);
        } else {
            $roles = Role::all()->pluck('name')->toArray();
            if (!empty($roles)) {
                $role = $this->choice('Assign a role to the user? (optional)', array_merge(['none'], $roles), 0);
                if ($role !== 'none') {
                    $this->assignRole($user, $role);
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Assign a role to the user
     */
    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            $this->warning("Role '{$roleName}' not found. Creating it...");
            $role = Role::create(['name' => $roleName]);
        }

        $user->assignRole($role);
        $this->success("Role '{$roleName}' assigned to user.");
    }
}
