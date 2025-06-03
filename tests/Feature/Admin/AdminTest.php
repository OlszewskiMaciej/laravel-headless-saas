<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Seeders\RolesAndPermissionsSeeder;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_list_users(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create some regular users
        User::factory()->count(5)->create()->each(function ($user) {
            $user->assignRole('free');
        });

        $response = $this->withApiToken($token)
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                        ]
                    ],
                    'meta' => [
                        'total',
                        'count',
                        'per_page',
                        'current_page',
                        'last_page',
                    ]
                ]
            ]);
    }

    public function test_regular_users_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('free');
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withApiToken($token)
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create a user to update
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);
        $user->assignRole('free');

        $response = $this->withApiToken($token)
            ->putJson("/api/admin/users/{$user->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'roles' => ['premium'],
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
        
        $user->refresh();
        $this->assertTrue($user->hasRole('premium'));
        $this->assertFalse($user->hasRole('free'));
    }
}
