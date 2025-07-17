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
            // Subscription permissions
            'start trial',
            'access free features',
            'access premium features',

            // Admin permissions
            //
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
            'access free features',
            'start trial',
        ]);
        
        // Premium role (after subscription)        
        $premiumRole = Role::firstOrCreate(['name' => 'premium']);
        $premiumRole->givePermissionTo([
            'access free features',
            'access premium features',
        ]);
        
        // Trial role (same permissions as premium except start trial)
        $trialRole = Role::firstOrCreate(['name' => 'trial']);
        $trialRole->givePermissionTo([
            'access free features',
            'access premium features',
        ]);
    }
}
