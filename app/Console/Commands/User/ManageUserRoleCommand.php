<?php

namespace App\Console\Commands\User;

use App\Console\Commands\BaseCommand;
use App\Models\User;
use App\Models\Role;

class ManageUserRoleCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:role 
                            {user : The user email or UUID}
                            {--assign= : Role to assign}
                            {--remove= : Role to remove}
                            {--sync= : Roles to sync (comma-separated)}
                            {--list : List user roles}
                            {--clear : Remove all roles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage user roles - assign, remove, sync, or list roles';

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

        $this->line("Managing roles for: {$user->name} ({$user->email})");

        // List current roles
        if ($this->option('list')) {
            return $this->listUserRoles($user);
        }

        // Clear all roles
        if ($this->option('clear')) {
            return $this->clearUserRoles($user);
        }

        // Assign role
        if ($assignRole = $this->option('assign')) {
            return $this->assignRole($user, $assignRole);
        }

        // Remove role
        if ($removeRole = $this->option('remove')) {
            return $this->removeRole($user, $removeRole);
        }

        // Sync roles
        if ($syncRoles = $this->option('sync')) {
            return $this->syncRoles($user, $syncRoles);
        }

        // Interactive mode
        return $this->interactiveMode($user);
    }

    /**
     * List user roles
     */
    private function listUserRoles(User $user): int
    {
        $roles = $user->roles;

        if ($roles->isEmpty()) {
            $this->warning('User has no roles assigned.');
        } else {
            $this->success('Current roles:');
            foreach ($roles as $role) {
                $this->line("  - {$role->name}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Clear all user roles
     */
    private function clearUserRoles(User $user): int
    {
        if ($this->confirm("Are you sure you want to remove all roles from {$user->name}?")) {
            $user->syncRoles([]);
            $this->success('All roles removed from user.');
        } else {
            $this->warning('Operation cancelled.');
        }

        return self::SUCCESS;
    }

    /**
     * Assign a role to the user
     */
    private function assignRole(User $user, string $roleName): int
    {
        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            if ($this->confirm("Role '{$roleName}' doesn't exist. Create it?")) {
                $role = Role::create(['name' => $roleName]);
                $this->success("Role '{$roleName}' created.");
            } else {
                $this->warning('Operation cancelled.');
                return self::FAILURE;
            }
        }

        if ($user->hasRole($roleName)) {
            $this->warning("User already has role '{$roleName}'.");
        } else {
            $user->assignRole($role);
            $this->success("Role '{$roleName}' assigned to user.");
        }

        return self::SUCCESS;
    }

    /**
     * Remove a role from the user
     */
    private function removeRole(User $user, string $roleName): int
    {
        if ($user->hasRole($roleName)) {
            $user->removeRole($roleName);
            $this->success("Role '{$roleName}' removed from user.");
        } else {
            $this->warning("User doesn't have role '{$roleName}'.");
        }

        return self::SUCCESS;
    }

    /**
     * Sync user roles
     */
    private function syncRoles(User $user, string $rolesString): int
    {
        $roleNames = array_map('trim', explode(',', $rolesString));
        $roles     = [];

        foreach ($roleNames as $roleName) {
            if (empty($roleName)) {
                continue;
            }

            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                if ($this->confirm("Role '{$roleName}' doesn't exist. Create it?")) {
                    $role = Role::create(['name' => $roleName]);
                    $this->success("Role '{$roleName}' created.");
                } else {
                    $this->warning("Skipping role '{$roleName}'.");
                    continue;
                }
            }

            $roles[] = $role;
        }

        $user->syncRoles($roles);
        $this->success('User roles synchronized.');

        // Show current roles
        $this->listUserRoles($user);

        return self::SUCCESS;
    }

    /**
     * Interactive role management
     */
    private function interactiveMode(User $user): int
    {
        $action = $this->choice(
            'What would you like to do?',
            ['List roles', 'Assign role', 'Remove role', 'Sync roles', 'Clear all roles', 'Cancel'],
            0
        );

        switch ($action) {
            case 'List roles':
                return $this->listUserRoles($user);

            case 'Assign role':
                $availableRoles = Role::all()->pluck('name')->toArray();
                $roleName       = $this->choice('Which role to assign?', array_merge($availableRoles, ['Create new role']));

                if ($roleName === 'Create new role') {
                    $roleName = $this->ask('Enter new role name:');
                }

                return $this->assignRole($user, $roleName);

            case 'Remove role':
                $userRoles = $user->roles->pluck('name')->toArray();

                if (empty($userRoles)) {
                    $this->warning('User has no roles to remove.');
                    return self::SUCCESS;
                }

                $roleName = $this->choice('Which role to remove?', $userRoles);
                return $this->removeRole($user, $roleName);

            case 'Sync roles':
                $rolesString = $this->ask('Enter roles to sync (comma-separated):');
                return $this->syncRoles($user, $rolesString);

            case 'Clear all roles':
                return $this->clearUserRoles($user);

            default:
                $this->warning('Operation cancelled.');
                return self::SUCCESS;
        }
    }
}
