<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User permissions
            'view own profile',
            'update own profile',
            
            // Subscription permissions
            'subscribe to plan',
            'cancel subscription',
            'resume subscription',
            'start trial',
            'get invoice',
            'access premium features',
            
            // Admin permissions
            'view users',
            'create users',
            'show users',
            'update users',
            'delete users',

            'view roles',
            'create roles',
            'update roles',
            'delete roles',
            
            'view activity logs',

            // API key permissions
            'manage api keys',
            'view api keys',
            'create api keys',
            'update api keys',
            'revoke api keys',
            'delete api keys',
        ];        
        
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all());
        
        // Free role (after registration)
        $freeRole = Role::firstOrCreate(['name' => 'free']);
        $freeRole->givePermissionTo([
            'view own profile', 
            'update own profile',
            'subscribe to plan',
            'start trial',
            'get invoice',
        ]);
        
        // Premium role (after subscription)        
        $premiumRole = Role::firstOrCreate(['name' => 'premium']);
        $premiumRole->givePermissionTo([
            'view own profile',
            'update own profile',
            'cancel subscription',
            'resume subscription',
            'get invoice',
            'access premium features',
        ]);
        
        // Trial role (same permissions as premium except start trial)
        $trialRole = Role::firstOrCreate(['name' => 'trial']);
        $trialRole->givePermissionTo([
            'view own profile',
            'update own profile',
            'get invoice',
            'access premium features',
            'subscribe to plan',
            'get invoice',
            'access premium features',
        ]);
    }
}
