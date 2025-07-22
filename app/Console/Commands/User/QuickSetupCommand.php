<?php

namespace App\Console\Commands\User;

use App\Console\Commands\BaseCommand;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class QuickSetupCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:quick-setup 
                            {--demo : Create demo users and data}
                            {--production : Setup for production environment}
                            {--roles-only : Only create roles and permissions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quick setup for user management system - creates roles, permissions, and demo users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('🚀 Starting Laravel Headless SaaS Setup...');

        if ($this->option('roles-only')) {
            return $this->setupRolesOnly();
        }

        if ($this->option('production')) {
            return $this->setupProduction();
        }

        if ($this->option('demo')) {
            return $this->setupDemo();
        }

        // Interactive setup
        return $this->interactiveSetup();
    }

    /**
     * Setup roles and permissions only
     */
    private function setupRolesOnly(): int
    {
        $this->createRolesAndPermissions();
        $this->success('✅ Roles and permissions setup completed!');
        return self::SUCCESS;
    }

    /**
     * Setup for production environment
     */
    private function setupProduction(): int
    {
        $this->line('🔧 Setting up production environment...');

        // Create roles and permissions
        $this->createRolesAndPermissions();

        // Create admin user
        $adminEmail    = $this->ask('Enter admin email:', 'admin@yourapp.com');
        $adminPassword = $this->secret('Enter admin password:');

        if (User::where('email', $adminEmail)->exists()) {
            $this->failure("User with email {$adminEmail} already exists!");
            return self::FAILURE;
        }

        $admin = User::create([
            'name'              => 'System Administrator',
            'email'             => $adminEmail,
            'password'          => Hash::make($adminPassword),
            'email_verified_at' => Carbon::now(),
            'trial_ends_at'     => Carbon::create(2037, 1, 1, 0, 0, 0), // Effectively unlimited
        ]);

        $admin->assignRole('admin');

        $this->success('✅ Production setup completed!');
        $this->line("Admin user created: {$adminEmail}");
        $this->warning('🔐 Please store the admin credentials securely!');

        return self::SUCCESS;
    }

    /**
     * Setup demo environment
     */
    private function setupDemo(): int
    {
        $this->line('🎯 Setting up demo environment...');

        // Create roles and permissions
        $this->createRolesAndPermissions();

        // Create demo users
        $this->createDemoUsers();

        $this->success('✅ Demo setup completed!');
        $this->line('Demo users created with various roles and trial statuses');
        $this->line("Run 'php artisan user:list' to see all users");

        return self::SUCCESS;
    }

    /**
     * Interactive setup
     */
    private function interactiveSetup(): int
    {
        $this->line('🎛️  Interactive Setup Mode');

        $setupType = $this->choice(
            'What type of setup would you like?',
            [
                'Full Demo Setup (roles + demo users)',
                'Production Setup (roles + admin user)',
                'Roles Only (just create roles and permissions)',
                'Custom Setup (choose what to create)'
            ]
        );

        switch ($setupType) {
            case 'Full Demo Setup (roles + demo users)':
                return $this->setupDemo();

            case 'Production Setup (roles + admin user)':
                return $this->setupProduction();

            case 'Roles Only (just create roles and permissions)':
                return $this->setupRolesOnly();

            case 'Custom Setup (choose what to create)':
                return $this->customSetup();
        }

        return self::SUCCESS;
    }

    /**
     * Custom setup with user choices
     */
    private function customSetup(): int
    {
        $this->line('🔧 Custom Setup Mode');

        // Ask what to create
        $createRoles = $this->confirm('Create default roles and permissions?', true);
        $createAdmin = $this->confirm('Create admin user?', true);
        $createDemo  = $this->confirm('Create demo users?', false);

        if ($createRoles) {
            $this->createRolesAndPermissions();
        }

        if ($createAdmin) {
            $this->createAdminUser();
        }

        if ($createDemo) {
            $this->createDemoUsers();
        }

        $this->success('✅ Custom setup completed!');
        return self::SUCCESS;
    }

    /**
     * Create default roles and permissions
     */
    private function createRolesAndPermissions(): void
    {
        $this->line('📝 Creating roles and permissions...');

        // Define roles and their permissions
        $rolesData = [
            'admin'   => [],
            'premium' => [
                'access free features',
                'access premium features'
            ],
            'trial' => [
                'access free features',
                'access premium features'
            ],
            'free' => [
                'access free features',
                'start trial'
            ]
        ];

        foreach ($rolesData as $roleName => $permissions) {
            // Create role if it doesn't exist
            $role = Role::firstOrCreate(['name' => $roleName]);

            // Create permissions and assign to role
            $rolePermissions = [];
            foreach ($permissions as $permissionName) {
                $permission        = Permission::firstOrCreate(['name' => $permissionName]);
                $rolePermissions[] = $permission;
            }

            $role->syncPermissions($rolePermissions);
            $this->line("  ✓ Role '{$roleName}' created with " . count($permissions) . ' permissions');
        }
    }

    /**
     * Create admin user
     */
    private function createAdminUser(): void
    {
        $this->line('👑 Creating admin user...');

        $adminEmail = $this->ask('Enter admin email:', 'admin@example.com');

        if (User::where('email', $adminEmail)->exists()) {
            $this->warning("Admin user with email {$adminEmail} already exists!");
            return;
        }

        $adminPassword = $this->secret('Enter admin password:') ?: 'admin123';

        $admin = User::create([
            'name'              => 'System Administrator',
            'email'             => $adminEmail,
            'password'          => Hash::make($adminPassword),
            'email_verified_at' => Carbon::now(),
            'trial_ends_at'     => Carbon::create(2037, 1, 1, 0, 0, 0), // Effectively unlimited
        ]);

        $admin->assignRole('admin');
        $this->line("  ✓ Admin user created: {$adminEmail}");
    }

    /**
     * Create demo users
     */
    private function createDemoUsers(): void
    {
        $this->line('🎭 Creating demo users...');

        $demoUsers = [
            [
                'name'       => 'John Admin',
                'email'      => 'admin@demo.com',
                'password'   => 'demo123',
                'role'       => 'admin',
                'trial_type' => 'unlimited'
            ],
            [
                'name'       => 'Jane Premium',
                'email'      => 'premium@demo.com',
                'password'   => 'demo123',
                'role'       => 'premium',
                'trial_type' => 'active'
            ],
            [
                'name'       => 'Alice Trial',
                'email'      => 'trial@demo.com',
                'password'   => 'demo123',
                'role'       => 'trial',
                'trial_type' => 'active'
            ],
            [
                'name'       => 'Charlie Free',
                'email'      => 'free@demo.com',
                'password'   => 'demo123',
                'role'       => 'free',
                'trial_type' => 'none'
            ],
            [
                'name'       => 'Diana Expired',
                'email'      => 'expired@demo.com',
                'password'   => 'demo123',
                'role'       => 'free',
                'trial_type' => 'expired'
            ]
        ];

        foreach ($demoUsers as $userData) {
            if (User::where('email', $userData['email'])->exists()) {
                $this->warning("Demo user {$userData['email']} already exists, skipping...");
                continue;
            }

            // Set trial based on type
            $trialEndsAt = null;
            switch ($userData['trial_type']) {
                case 'unlimited':
                    $trialEndsAt = Carbon::create(2037, 1, 1, 0, 0, 0); // Effectively unlimited
                    break;
                case 'active':
                    $trialEndsAt = Carbon::now()->addDays(30);
                    break;
                case 'expired':
                    $trialEndsAt = Carbon::now()->subDays(10);
                    break;
                case 'none':
                default:
                    $trialEndsAt = null;
                    break;
            }

            $user = User::create([
                'name'              => $userData['name'],
                'email'             => $userData['email'],
                'password'          => Hash::make($userData['password']),
                'email_verified_at' => Carbon::now(),
                'trial_ends_at'     => $trialEndsAt,
            ]);

            $user->assignRole($userData['role']);
            $this->line("  ✓ Demo user created: {$userData['name']} ({$userData['email']}) - {$userData['role']} - {$userData['trial_type']} trial");
        }
    }
}
