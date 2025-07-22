<?php

namespace App\Console\Commands\Role;

use App\Console\Commands\BaseCommand;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class ManageRoleCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'role:manage 
                            {action : The action to perform (create, delete, list, permissions)}
                            {role? : The role name}
                            {--permissions= : Comma-separated list of permissions}
                            {--force : Force the action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage roles and permissions - create, delete, list, and assign permissions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'create':
                return $this->createRole();
            case 'delete':
                return $this->deleteRole();
            case 'list':
                return $this->listRoles();
            case 'permissions':
                return $this->managePermissions();
            default:
                $this->failure("Invalid action: {$action}");
                $this->line('Available actions: create, delete, list, permissions');
                return self::FAILURE;
        }
    }

    /**
     * Create a new role
     */
    private function createRole(): int
    {
        $roleName = $this->argument('role') ?: $this->ask('Enter role name:');

        if (Role::where('name', $roleName)->exists()) {
            $this->failure("Role '{$roleName}' already exists.");
            return self::FAILURE;
        }

        $role = Role::create(['name' => $roleName]);
        $this->success("Role '{$roleName}' created successfully.");

        // Assign permissions if provided
        if ($permissions = $this->option('permissions')) {
            $this->assignPermissions($role, $permissions);
        } else {
            if ($this->confirm('Would you like to assign permissions to this role?')) {
                $this->manageRolePermissions($role);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Delete a role
     */
    private function deleteRole(): int
    {
        $roleName = $this->argument('role') ?: $this->ask('Enter role name to delete:');

        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            $this->failure("Role '{$roleName}' not found.");
            return self::FAILURE;
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            $this->warning("This role is assigned to {$userCount} user(s).");
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete role '{$roleName}'?")) {
                $this->warning('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $role->delete();
        $this->success("Role '{$roleName}' deleted successfully.");

        return self::SUCCESS;
    }

    /**
     * List all roles
     */
    private function listRoles(): int
    {
        $roles = Role::with('permissions', 'users')->get();

        if ($roles->isEmpty()) {
            $this->warning('No roles found.');
            return self::SUCCESS;
        }

        $this->line('=== Roles ===');

        foreach ($roles as $role) {
            $this->line("Role: {$role->name}");
            $this->line('  Users: ' . $role->users->count());
            $this->line('  Permissions: ' . $role->permissions->pluck('name')->implode(', '));
            $this->line('');
        }

        return self::SUCCESS;
    }

    /**
     * Manage role permissions
     */
    private function managePermissions(): int
    {
        $roleName = $this->argument('role') ?: $this->ask('Enter role name:');

        $role = Role::where('name', $roleName)->first();
        if (!$role) {
            $this->failure("Role '{$roleName}' not found.");
            return self::FAILURE;
        }

        if ($permissions = $this->option('permissions')) {
            return $this->assignPermissions($role, $permissions);
        }

        return $this->manageRolePermissions($role);
    }

    /**
     * Assign permissions to a role
     */
    private function assignPermissions(Role $role, string $permissionsString): int
    {
        $permissionNames = array_map('trim', explode(',', $permissionsString));
        $permissions     = [];

        foreach ($permissionNames as $permissionName) {
            if (empty($permissionName)) {
                continue;
            }

            $permission = Permission::where('name', $permissionName)->first();

            if (!$permission) {
                if ($this->confirm("Permission '{$permissionName}' doesn't exist. Create it?")) {
                    $permission = Permission::create(['name' => $permissionName]);
                    $this->success("Permission '{$permissionName}' created.");
                } else {
                    $this->warning("Skipping permission '{$permissionName}'.");
                    continue;
                }
            }

            $permissions[] = $permission;
        }

        $role->syncPermissions($permissions);
        $this->success("Permissions assigned to role '{$role->name}'.");

        return self::SUCCESS;
    }

    /**
     * Interactive permission management
     */
    private function manageRolePermissions(Role $role): int
    {
        $action = $this->choice(
            "What would you like to do with permissions for role '{$role->name}'?",
            ['List current permissions', 'Add permission', 'Remove permission', 'Sync permissions', 'Cancel']
        );

        switch ($action) {
            case 'List current permissions':
                $permissions = $role->permissions->pluck('name')->toArray();
                if (empty($permissions)) {
                    $this->warning('No permissions assigned to this role.');
                } else {
                    $this->success('Current permissions:');
                    foreach ($permissions as $permission) {
                        $this->line("  - {$permission}");
                    }
                }
                break;

            case 'Add permission':
                $permissionName = $this->ask('Enter permission name:');
                $permission     = Permission::firstOrCreate(['name' => $permissionName]);
                $role->givePermissionTo($permission);
                $this->success("Permission '{$permissionName}' added to role.");
                break;

            case 'Remove permission':
                $rolePermissions = $role->permissions->pluck('name')->toArray();
                if (empty($rolePermissions)) {
                    $this->warning('No permissions to remove.');
                } else {
                    $permissionName = $this->choice('Which permission to remove?', $rolePermissions);
                    $role->revokePermissionTo($permissionName);
                    $this->success("Permission '{$permissionName}' removed from role.");
                }
                break;

            case 'Sync permissions':
                $permissionsString = $this->ask('Enter permissions to sync (comma-separated):');
                $this->assignPermissions($role, $permissionsString);
                break;

            default:
                $this->warning('Operation cancelled.');
        }

        return self::SUCCESS;
    }
}
