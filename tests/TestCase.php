<?php

namespace Tests;

use Database\Seeders\DefaultApiKeySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    
    /**
     * Default API key to use for tests
     */
    protected string $defaultApiKey;
    
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions for all tests
        $this->seed(RolesAndPermissionsSeeder::class);
        
        // Seed default API keys for tests
        $this->seed(DefaultApiKeySeeder::class);
        
        // Set the default API key for tests
        $this->defaultApiKey = DefaultApiKeySeeder::DEFAULT_KEYS['TEST_DEV'];
    }
    
    /**
     * Override the getJson method to include API key.
     *
     * @param string $uri
     * @param array $headers
     * @param int $options
     * @return TestResponse
     */
    public function getJson($uri, array $headers = [], $options = 0)
    {
        return parent::getJson($uri, $this->addApiKeyHeader($headers), $options);
    }
    
    /**
     * Override the postJson method to include API key.
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param int $options
     * @return TestResponse
     */
    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::postJson($uri, $data, $this->addApiKeyHeader($headers), $options);
    }
    
    /**
     * Override the putJson method to include API key.
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param int $options
     * @return TestResponse
     */
    public function putJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::putJson($uri, $data, $this->addApiKeyHeader($headers), $options);
    }
    
    /**
     * Override the patchJson method to include API key.
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param int $options
     * @return TestResponse
     */
    public function patchJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::patchJson($uri, $data, $this->addApiKeyHeader($headers), $options);
    }
    
    /**
     * Override the deleteJson method to include API key.
     *
     * @param string $uri
     * @param array $data
     * @param array $headers
     * @param int $options
     * @return TestResponse
     */
    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        return parent::deleteJson($uri, $data, $this->addApiKeyHeader($headers), $options);
    }
    
    /**
     * Add API key header to the request.
     *
     * @param array $headers
     * @return array
     */
    protected function addApiKeyHeader(array $headers = []): array
    {
        return array_merge($headers, [
            'X-API-KEY' => $this->defaultApiKey
        ]);
    }
    
    /**
     * Create a new withHeader instance that includes API key.
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function withApiToken(string $token)
    {
        return $this->withHeader('Authorization', "Bearer {$token}")
                    ->withHeader('X-API-KEY', $this->defaultApiKey);
    }
}
