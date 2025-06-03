<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['free'],
        ];

        $response = $this->withApiToken($token)
            ->postJson('/api/admin/users', $userData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'roles'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
        ]);

        // Check that the user has the assigned role
        $user = User::where('email', 'testuser@example.com')->first();
        $this->assertTrue($user->hasRole('free'));
    }

    public function test_regular_user_cannot_create_users(): void
    {
        // Create a regular user
        $user = User::factory()->create();
        $user->assignRole('free');
        $token = $user->createToken('auth_token')->plainTextToken;

        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['free'],
        ];

        $response = $this->withApiToken($token)
            ->postJson('/api/admin/users', $userData);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_create_user_with_existing_email(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create a user with the email we'll try to use again
        User::factory()->create(['email' => 'existing@example.com']);

        $userData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['free'],
        ];

        $response = $this->withApiToken($token)
            ->postJson('/api/admin/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_cannot_assign_nonexistent_roles(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['nonexistent-role'],
        ];

        $response = $this->withApiToken($token)
            ->postJson('/api/admin/users', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['roles.0']);
    }
}
