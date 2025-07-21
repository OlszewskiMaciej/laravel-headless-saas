<?php

namespace Tests\Unit\Services;

use App\Console\Commands\ApiKey\Repositories\Interfaces\ApiKeyRepositoryInterface;
use App\Models\ApiKey;
use App\Console\Commands\ApiKey\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test creating a new API key
     */
    public function test_can_create_api_key(): void
    {
        // Create a mock of the ApiKeyRepositoryInterface
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        
        // Set up expectations for the repository mock
        $repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function ($data) {
                $apiKey = new ApiKey($data);
                $apiKey->uuid = 1; // Mock the ID
                return $apiKey;
            });
            
        $service = new ApiKeyService($repository);
        
        $result = $service->createKey(
            'Test API Key',
            'testing-service',
            'testing',
            'API key for testing purposes',
            now()->addDays(30)
        );
        
        // Check that the result contains both the model and the plain text key
        $this->assertArrayHasKey('api_key', $result);
        $this->assertArrayHasKey('plain_text_key', $result);
        $this->assertInstanceOf(ApiKey::class, $result['api_key']);
        $this->assertIsString($result['plain_text_key']);
        
        // Verify that the key in the database is the hashed version of the plain text key
        $this->assertEquals(
            ApiKey::hashKey($result['plain_text_key']),
            $result['api_key']->key
        );
    }
    
    /**
     * Test validating a valid API key
     */
    public function test_can_validate_valid_api_key(): void
    {
        // Create a key
        $plainTextKey = 'test-api-key-' . time();
        $hashedKey = ApiKey::hashKey($plainTextKey);
        
        $apiKey = new ApiKey([
            'name' => 'Test API Key',
            'key' => $hashedKey,
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);
        
        // Create a mock repository
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $repository->shouldReceive('findByKey')
            ->with($hashedKey)
            ->once()
            ->andReturn($apiKey);
            
        $repository->shouldReceive('update')
            ->once()
            ->andReturn(true);
            
        $service = new ApiKeyService($repository);
        
        // Validate the key
        $keyModel = $service->validateKey($plainTextKey);
        
        $this->assertInstanceOf(ApiKey::class, $keyModel);
        $this->assertEquals('Test API Key', $keyModel->name);
        $this->assertEquals('testing-service', $keyModel->service);
    }
    
    /**
     * Test validating an invalid API key
     */
    public function test_cannot_validate_invalid_api_key(): void
    {
        // Create a mock repository
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $repository->shouldReceive('findByKey')
            ->once()
            ->andReturn(null);
            
        $service = new ApiKeyService($repository);
        
        // Try to validate with an invalid key
        $result = $service->validateKey('invalid-key');
        
        $this->assertNull($result);
    }
    
    /**
     * Test validating an inactive API key
     */
    public function test_cannot_validate_inactive_api_key(): void
    {
        // Create a key that's inactive
        $plainTextKey = 'test-api-key-' . time();
        $hashedKey = ApiKey::hashKey($plainTextKey);
        
        $apiKey = new ApiKey([
            'name' => 'Test API Key',
            'key' => $hashedKey,
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => false,
        ]);
        
        // Create a mock repository
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $repository->shouldReceive('findByKey')
            ->with($hashedKey)
            ->once()
            ->andReturn($apiKey);
            
        $service = new ApiKeyService($repository);
        
        // Validate the key
        $result = $service->validateKey($plainTextKey);
        
        $this->assertNull($result);
    }
    
    /**
     * Test validating an expired API key
     */
    public function test_cannot_validate_expired_api_key(): void
    {
        // Create a key that's expired
        $plainTextKey = 'test-api-key-' . time();
        $hashedKey = ApiKey::hashKey($plainTextKey);
        
        $apiKey = new ApiKey([
            'name' => 'Test API Key',
            'key' => $hashedKey,
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
            'expires_at' => now()->subDays(1),
        ]);
        
        // Create a mock repository
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $repository->shouldReceive('findByKey')
            ->with($hashedKey)
            ->once()
            ->andReturn($apiKey);
            
        $service = new ApiKeyService($repository);
        
        // Validate the key
        $result = $service->validateKey($plainTextKey);
        
        $this->assertNull($result);
    }
    
    /**
     * Test extracting API key from header
     */
    public function test_can_extract_api_key_from_header(): void
    {
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $service = new ApiKeyService($repository);
        
        $request = new Request();
        $request->headers->set('X-API-KEY', 'test-key-123');
        
        $key = $service->extractKeyFromRequest($request);
        
        $this->assertEquals('test-key-123', $key);
    }
    
    /**
     * Test extracting API key from query parameter
     */
    public function test_can_extract_api_key_from_query_parameter(): void
    {
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $service = new ApiKeyService($repository);
        
        $request = new Request(['api_key' => 'test-key-123']);
        
        $key = $service->extractKeyFromRequest($request);
        
        $this->assertEquals('test-key-123', $key);
    }
    
    /**
     * Test revoking an API key
     */
    public function test_can_revoke_api_key(): void
    {
        $apiKey = new ApiKey([
            'name' => 'Test API Key',
            'key' => 'hashed-key-123',
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);
        
        // Create a mock repository
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $repository->shouldReceive('deactivate')
            ->with($apiKey)
            ->once()
            ->andReturn(true);
            
        $service = new ApiKeyService($repository);
        
        // Revoke the key
        Cache::shouldReceive('forget')
            ->once()
            ->with('api_key:hashed-key-123')
            ->andReturn(true);
            
        $result = $service->revokeKey($apiKey);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test deleting an API key
     */
    public function test_can_delete_api_key(): void
    {
        $apiKey = new ApiKey([
            'name' => 'Test API Key',
            'key' => 'hashed-key-123',
            'service' => 'testing-service',
            'environment' => 'testing',
            'description' => 'API key for testing purposes',
            'is_active' => true,
        ]);
        
        // Create a mock repository
        $repository = Mockery::mock(ApiKeyRepositoryInterface::class);
        $repository->shouldReceive('delete')
            ->with($apiKey)
            ->once()
            ->andReturn(true);
            
        $service = new ApiKeyService($repository);
        
        // Delete the key
        Cache::shouldReceive('forget')
            ->once()
            ->with('api_key:hashed-key-123')
            ->andReturn(true);
            
        $result = $service->deleteKey($apiKey);
        
        $this->assertTrue($result);
    }
}
