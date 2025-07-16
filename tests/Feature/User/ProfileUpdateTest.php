<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Database\Seeders\RolesAndPermissionsSeeder;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed the roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Test a user can update their profile
     */
    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $user->assignRole('free');

        $response = $this->actingAs($user)
            ->putJson('/api/user/profile', [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'uuid' => $user->uuid,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /**
     * Test a user can change their password
     */    
    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);
        $user->assignRole('free');

        $response = $this->actingAs($user)
            ->putJson('/api/user/profile', [
                'current_password' => 'current-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Profile updated successfully',
            ]);

        // Verify the password was actually changed
        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertFalse(Hash::check('current-password', $user->password));
    }

    /**
     * Test validation requires current password when changing password
     */
    public function test_current_password_required_when_changing_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/user/profile', [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * Test validation requires correct current password when changing password
     */
    public function test_current_password_must_be_correct(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('real-current-password'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/user/profile', [
                'current_password' => 'wrong-current-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Verify the password was not changed
        $user->refresh();
        $this->assertTrue(Hash::check('real-current-password', $user->password));
        $this->assertFalse(Hash::check('new-password', $user->password));
    }

    /**
     * Test validation requires password confirmation to match
     */
    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $response = $this->actingAs($user)
            ->putJson('/api/user/profile', [
                'current_password' => 'current-password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Verify the password was not changed
        $user->refresh();
        $this->assertTrue(Hash::check('current-password', $user->password));
    }

    /**
     * Test a user can update profile and change password simultaneously
     */    
    public function test_user_can_update_profile_and_change_password_simultaneously(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('current-password'),
        ]);
        $user->assignRole('free');
        $user->assignRole('free');

        $response = $this->actingAs($user)
            ->putJson('/api/user/profile', [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'current_password' => 'current-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                ],
            ]);

        // Verify both profile and password were updated
        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('updated@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_users_can_get_their_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('free');
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withApiToken($token)
            ->getJson('/api/user/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'uuid',
                    'name',
                    'email',
                ]
            ]);
    }
}
