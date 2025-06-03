<?php

namespace Tests\Feature\ApiKey;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can create a new API key
     */
    public function test_admin_can_create_api_key(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        $apiKeyData = [
            'name' => 'Test API Key',
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'expires_days' => 30,
        ];

        $response = $this->withApiToken($token)
            ->postJson('/api/admin/api-keys', $apiKeyData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'api_key',
                    'plain_text_key',
                ],
            ]);

        // Verify data was saved correctly (excluding the plain text key)
        $this->assertDatabaseHas('api_keys', [
            'name' => 'Test API Key',
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);

        // Ensure the plain text key was returned
        $this->assertNotNull($response->json('data.plain_text_key'));
    }

    /**
     * Test non-admin user cannot create API keys
     */
    public function test_regular_user_cannot_create_api_key(): void
    {
        // Create a regular user
        $user = User::factory()->create();
        $user->assignRole('free');
        $token = $user->createToken('auth_token')->plainTextToken;

        $apiKeyData = [
            'name' => 'Test API Key',
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'expires_days' => 30,
        ];

        $response = $this->withApiToken($token)
            ->postJson('/api/admin/api-keys', $apiKeyData);

        $response->assertStatus(403);
    }

    /**
     * Test admin can retrieve all API keys
     */
    public function test_admin_can_list_api_keys(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create a few API keys
        ApiKey::create([
            'name' => 'Test API Key 1',
            'key' => ApiKey::hashKey('test-key-1'),
            'service' => 'testing-service',
            'environment' => 'development',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);

        ApiKey::create([
            'name' => 'Test API Key 2',
            'key' => ApiKey::hashKey('test-key-2'),
            'service' => 'testing-service',
            'environment' => 'production',
            'description' => 'Another API key for testing purposes',
            'is_active' => true,
        ]);

        $response = $this->withApiToken($token)
            ->getJson('/api/admin/api-keys');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data', // Paginated data
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        // Verify we have at least 2 API keys + the default test keys
        $this->assertGreaterThanOrEqual(2, count($response->json('data.data')));
    }

    /**
     * Test admin can get a specific API key
     */
    public function test_admin_can_view_api_key(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create an API key
        $apiKey = ApiKey::create([
            'name' => 'Test API Key',
            'key' => ApiKey::hashKey('test-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);

        $response = $this->withApiToken($token)
            ->getJson("/api/admin/api-keys/{$apiKey->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'API key retrieved successfully',
                'data' => [
                    'name' => 'Test API Key',
                    'service' => 'testing-service',
                    'environment' => 'testing',
                    'description' => 'API key for testing purposes',
                    'is_active' => true,
                ],
            ]);

        // Ensure the key is not exposed
        $this->assertArrayNotHasKey('key', $response->json('data'));
    }

    /**
     * Test admin can update an API key
     */
    public function test_admin_can_update_api_key(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create an API key
        $apiKey = ApiKey::create([
            'name' => 'Test API Key',
            'key' => ApiKey::hashKey('test-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);

        $updateData = [
            'name' => 'Updated API Key',
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $response = $this->withApiToken($token)
            ->putJson("/api/admin/api-keys/{$apiKey->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'API key updated successfully',
                'data' => [
                    'name' => 'Updated API Key',
                    'description' => 'Updated description',
                    'is_active' => false,
                ],
            ]);

        // Verify the data was updated in the database
        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'name' => 'Updated API Key',
            'description' => 'Updated description',
            'is_active' => false,
        ]);
    }

    /**
     * Test admin can revoke an API key
     */
    public function test_admin_can_revoke_api_key(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create an API key
        $apiKey = ApiKey::create([
            'name' => 'Test API Key',
            'key' => ApiKey::hashKey('test-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);

        $response = $this->withApiToken($token)
            ->postJson("/api/admin/api-keys/{$apiKey->id}/revoke");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'API key revoked successfully',
            ]);

        // Verify the key was revoked in the database
        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'is_active' => false,
        ]);
    }

    /**
     * Test admin can delete an API key
     */
    public function test_admin_can_delete_api_key(): void
    {
        // Create an admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('auth_token')->plainTextToken;

        // Create an API key
        $apiKey = ApiKey::create([
            'name' => 'Test API Key',
            'key' => ApiKey::hashKey('test-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);

        $response = $this->withApiToken($token)
            ->deleteJson("/api/admin/api-keys/{$apiKey->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'API key deleted successfully',
            ]);

        // Verify the key was soft deleted in the database
        $this->assertSoftDeleted('api_keys', [
            'id' => $apiKey->id,
        ]);
    }
}
