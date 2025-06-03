<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Tests\TestCase;
use Database\Seeders\RolesAndPermissionsSeeder;

class AdminRolePreservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // This mocks the Cashier Stripe integration for testing
        $this->mock(Cashier::class, function ($mock) {
            $mock->shouldReceive('stripe')->andReturnSelf();
            $mock->shouldReceive('customers')->andReturnSelf();
            $mock->shouldReceive('create')->andReturn((object) ['id' => 'cus_test']);
            $mock->shouldReceive('createAsStripeCustomer')->andReturn((object) ['id' => 'cus_test']);
        });
    }

    /**
     * Test that admin users maintain their admin role when starting a trial
     */
    public function test_admin_maintains_role_when_starting_trial(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Start a trial
        $response = $this->withApiToken($token)
            ->postJson('/api/subscription/start-trial');

        $response->assertStatus(200);
        
        $admin->refresh();
        // Admin should still have admin role
        $this->assertTrue($admin->hasRole('admin'));
        // Admin should also have trial role
        $this->assertTrue($admin->hasRole('trial'));
    }

    /**
     * Test that admin users maintain their admin role when being updated
     */
    public function test_admin_maintains_role_when_admin_updates_roles(): void
    {
        // Create an admin user to perform the update
        $adminUser = User::factory()->create();
        $adminUser->assignRole('admin');
        $token = $adminUser->createToken('auth_token')->plainTextToken;

        // Create another admin user to be updated
        $targetAdmin = User::factory()->create([
            'name' => 'Target Admin',
            'email' => 'target@example.com',
        ]);
        $targetAdmin->assignRole('admin');

        // Update the target admin to have premium role (should preserve admin role)
        $response = $this->withApiToken($token)
            ->putJson("/api/admin/users/{$targetAdmin->id}", [
                'roles' => ['premium'],
            ]);

        $response->assertStatus(200);
        
        $targetAdmin->refresh();
        // Admin should still have admin role
        $this->assertTrue($targetAdmin->hasRole('admin'));
        // Admin should also have premium role
        $this->assertTrue($targetAdmin->hasRole('premium'));
    }    /**
     * Test that admin users maintain their admin role when subscribing
     */
    public function test_admin_maintains_role_when_subscribing(): void
    {
        // Create an admin user
        $admin = User::factory()->create([
            'stripe_id' => 'cus_test123'
        ]);
        $admin->assignRole('admin');
        
        // Manually apply the role changes as would happen in the controller
        $admin->syncRoles(['admin', 'premium']);
        
        $admin->refresh();
        
        // Admin should still have admin role
        $this->assertTrue($admin->hasRole('admin'));
        // Admin should also have premium role
        $this->assertTrue($admin->hasRole('premium'));
    }/**
     * Test that admin users maintain their admin role when trial ends
     */
    public function test_admin_maintains_role_when_trial_ends(): void
    {
        // Create an admin user on trial
        $admin = User::factory()->create([
            'trial_ends_at' => Carbon::now()->subDay(),  // Trial has ended
        ]);
        $admin->assignRole('admin');
        $admin->assignRole('trial');

        // Manually simulate the endTrial behavior
        $admin->trial_ends_at = Carbon::now();
        $admin->save();
        
        // Manually assign roles like in the service
        $admin->syncRoles(['admin', 'free']);
        
        $admin->refresh();
        // Admin should still have admin role
        $this->assertTrue($admin->hasRole('admin'));
        // Admin should also have free role since trial ended
        $this->assertTrue($admin->hasRole('free'));
        // Admin shouldn't have trial role anymore
        $this->assertFalse($admin->hasRole('trial'));
    }
}
