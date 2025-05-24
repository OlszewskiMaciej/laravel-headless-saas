<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;

class DefaultApiKeySeeder extends Seeder
{
    /**
     * Store default API keys in a constant for easy access.
     * IMPORTANT: These keys are for testing/development only! In production,
     * API keys should be generated uniquely and stored securely.
     */
    public const DEFAULT_KEYS = [
        'TEST_DEV' => 'test_dev_default_api_key',
        'WEB_FRONTEND_DEV' => 'web_frontend_dev_default_api_key',
        'MOBILE_APP_DEV' => 'mobile_app_dev_default_api_key',
    ];
    
    /**
     * Seed the default API keys.
     */
    public function run(): void
    {
        // Check if keys already exist
        $existingCount = ApiKey::count();
        if ($existingCount > 0) {
            $this->command->info('API keys already exist. Skipping default API key creation.');
            return;
        }

        // Test (Development)
        ApiKey::create([
            'name' => 'Test (Development)',
            'key' => ApiKey::hashKey(self::DEFAULT_KEYS['TEST_DEV']),
            'service' => 'test',
            'environment' => 'development',
            'description' => 'Default API key for testing in development environment',
            'expires_at' => null,
            'is_active' => true,
        ]);
        
        // Web Frontend (Development)
        ApiKey::create([
            'name' => 'Web Frontend (Development)',
            'key' => ApiKey::hashKey(self::DEFAULT_KEYS['WEB_FRONTEND_DEV']),
            'service' => 'web-frontend',
            'environment' => 'development',
            'description' => 'Default API key for web frontend in development environment',
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
        
        // Mobile App (Development)
        ApiKey::create([
            'name' => 'Mobile App (Development)',
            'key' => ApiKey::hashKey(self::DEFAULT_KEYS['MOBILE_APP_DEV']),
            'service' => 'mobile-app',
            'environment' => 'development',
            'description' => 'Default API key for mobile app in development environment',
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);
    }
}
