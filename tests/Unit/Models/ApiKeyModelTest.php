<?php

namespace Tests\Unit\Models;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test API key model uses UUID as primary key
     */
    public function test_api_key_uses_uuid(): void
    {
        $apiKey = ApiKey::create([
            'name' => 'Test UUID API Key',
            'key' => ApiKey::hashKey('test-uuid-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'Testing UUID generation',
            'is_active' => true,
        ]);
        
        // The key should be a UUID, not an auto-incrementing integer
        $this->assertTrue(
            preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $apiKey->uuid) === 1
        );
        
        // And it should not be incrementing
        $this->assertFalse($apiKey->getIncrementing());
        
        // Key type should be string
        $this->assertEquals('string', $apiKey->getKeyType());
    }

    /**
     * Test generating a new API key
     */
    public function test_can_generate_api_key(): void
    {
        $key = ApiKey::generateKey();
        
        // The generated key should be a string
        $this->assertIsString($key);
        
        // Should be 32 characters long
        $this->assertEquals(32, strlen($key));
    }

    /**
     * Test hashing an API key
     */
    public function test_can_hash_api_key(): void
    {
        $plainTextKey = 'test-api-key';
        $hashedKey = ApiKey::hashKey($plainTextKey);
        
        // The hashed key should be a string
        $this->assertIsString($hashedKey);
        
        // Should be a valid SHA-256 hash (64 characters)
        $this->assertEquals(64, strlen($hashedKey));
        
        // Hashing the same key again should produce the same hash
        $this->assertEquals($hashedKey, ApiKey::hashKey($plainTextKey));
        
        // Hashing a different key should produce a different hash
        $this->assertNotEquals($hashedKey, ApiKey::hashKey('different-key'));
    }

    /**
     * Test active scope filters out inactive API keys
     */
    public function test_active_scope_filters_inactive_keys(): void
    {
        // Create an active key
        ApiKey::create([
            'name' => 'Active API Key',
            'key' => ApiKey::hashKey('active-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'Active API key',
            'is_active' => true,
        ]);
        
        // Create an inactive key
        ApiKey::create([
            'name' => 'Inactive API Key',
            'key' => ApiKey::hashKey('inactive-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'Inactive API key',
            'is_active' => false,
        ]);
        
        // Get active keys
        $activeKeys = ApiKey::active()->get();
        
        // Should find at least one key (plus the default test keys)
        $this->assertTrue($activeKeys->count() >= 1);
        
        // None of the active keys should be inactive
        $this->assertTrue($activeKeys->where('is_active', false)->isEmpty());
    }

    /**
     * Test active scope filters out expired API keys
     */
    public function test_active_scope_filters_expired_keys(): void
    {
        // Create a non-expired key
        ApiKey::create([
            'name' => 'Non-expired API Key',
            'key' => ApiKey::hashKey('non-expired-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'Non-expired API key',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        
        // Create an expired key
        ApiKey::create([
            'name' => 'Expired API Key',
            'key' => ApiKey::hashKey('expired-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'Expired API key',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);
        
        // Get active keys
        $activeKeys = ApiKey::active()->get();
        
        // Check if any active key is expired
        $hasExpiredKeys = $activeKeys->filter(function ($key) {
            return $key->expires_at && $key->expires_at->isPast();
        })->isNotEmpty();
        
        // No active keys should be expired
        $this->assertFalse($hasExpiredKeys);
    }

    /**
     * Test isExpired method correctly identifies expired keys
     */
    public function test_is_expired_method(): void
    {
        // Create a key with no expiration
        $noExpirationKey = ApiKey::create([
            'name' => 'No Expiration API Key',
            'key' => ApiKey::hashKey('no-expiration-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key with no expiration',
            'is_active' => true,
            'expires_at' => null,
        ]);
        
        // Create a key with future expiration
        $futureExpirationKey = ApiKey::create([
            'name' => 'Future Expiration API Key',
            'key' => ApiKey::hashKey('future-expiration-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key with future expiration',
            'is_active' => true,
            'expires_at' => now()->addDay(),
        ]);
        
        // Create a key with past expiration
        $pastExpirationKey = ApiKey::create([
            'name' => 'Past Expiration API Key',
            'key' => ApiKey::hashKey('past-expiration-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key with past expiration',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);
        
        // Test isExpired method
        $this->assertFalse($noExpirationKey->isExpired());
        $this->assertFalse($futureExpirationKey->isExpired());
        $this->assertTrue($pastExpirationKey->isExpired());
    }

    /**
     * Test soft deleting API keys
     */
    public function test_api_key_uses_soft_deletes(): void
    {
        $apiKey = ApiKey::create([
            'name' => 'Soft Delete Test API Key',
            'key' => ApiKey::hashKey('soft-delete-test-key'),
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'Testing soft deletes',
            'is_active' => true,
        ]);
        
        // Verify the key exists
        $this->assertDatabaseHas('api_keys', [
            'uuid' => $apiKey->uuid,
        ]);
        
        // Delete the key
        $apiKey->delete();
        
        // The key should still exist in the database (soft deleted)
        $this->assertDatabaseHas('api_keys', [
            'uuid' => $apiKey->uuid,
        ]);
        
        // But should not be retrievable without withTrashed()
        $this->assertNull(ApiKey::find($apiKey->uuid));
        
        // Should be retrievable with withTrashed()
        $this->assertNotNull(ApiKey::withTrashed()->find($apiKey->uuid));
        
        // Should have a deleted_at timestamp
        $this->assertNotNull(ApiKey::withTrashed()->find($apiKey->uuid)->deleted_at);
    }
}
