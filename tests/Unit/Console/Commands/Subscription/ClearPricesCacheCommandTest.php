<?php

namespace Tests\Unit\Console\Commands\Subscription;

use App\Console\Commands\Subscription\ClearPricesCacheCommand;
use PHPUnit\Framework\TestCase as BaseTestCase;

class ClearPricesCacheCommandTest extends BaseTestCase
{
    /**
     * Test that command signature is correct
     */
    public function test_command_signature(): void
    {
        $command = new ClearPricesCacheCommand();
        
        $this->assertEquals('subscription:clear-prices-cache', $command->getName());
        $this->assertEquals('Clear cached Stripe prices data and related subscription cache', $command->getDescription());
        
        // Check that all expected options are defined
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('all'));
        $this->assertTrue($definition->hasOption('pattern'));
    }

    /**
     * Test that command extends BaseCommand
     */
    public function test_command_extends_base_command(): void
    {
        $command = new ClearPricesCacheCommand();
        
        $this->assertInstanceOf(\App\Console\Commands\BaseCommand::class, $command);
    }

    /**
     * Test that command options are properly configured
     */
    public function test_command_options_configuration(): void
    {
        $command = new ClearPricesCacheCommand();
        $definition = $command->getDefinition();
        
        // Test option configurations
        $allOption = $definition->getOption('all');
        $this->assertFalse($allOption->isValueRequired()); // Flag option
        $this->assertEquals('Clear all subscription-related cache', $allOption->getDescription());
        
        $patternOption = $definition->getOption('pattern');
        $this->assertFalse($patternOption->isValueRequired()); // Optional value
        $this->assertEquals('Clear cache matching specific pattern', $patternOption->getDescription());
    }

    /**
     * Test that command has proper return constants
     */
    public function test_command_return_constants(): void
    {
        $command = new ClearPricesCacheCommand();
        
        $this->assertEquals(0, $command::SUCCESS);
        $this->assertEquals(1, $command::FAILURE);
    }

    /**
     * Test command handle method exists
     */
    public function test_command_handle_method_exists(): void
    {
        $command = new ClearPricesCacheCommand();
        
        $this->assertTrue(method_exists($command, 'handle'));
        
        $reflection = new \ReflectionMethod($command, 'handle');
        $returnType = $reflection->getReturnType();
        
        $this->assertEquals('int', $returnType->getName());
    }

    /**
     * Test that cache patterns are properly defined
     */
    public function test_cache_patterns_defined(): void
    {
        $command = new ClearPricesCacheCommand();
        
        $reflection = new \ReflectionClass($command);
        $this->assertTrue($reflection->hasConstant('CACHE_PATTERNS'));
        
        $patterns = $reflection->getConstant('CACHE_PATTERNS');
        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('stripe_prices', $patterns);
        $this->assertArrayHasKey('subscription_plans', $patterns);
        $this->assertArrayHasKey('product_features', $patterns);
        $this->assertArrayHasKey('pricing_tiers', $patterns);
    }

    /**
     * Test that private methods exist for different cache clearing operations
     */
    public function test_private_methods_exist(): void
    {
        $command = new ClearPricesCacheCommand();
        
        $reflection = new \ReflectionClass($command);
        
        // Check that private methods exist
        $this->assertTrue($reflection->hasMethod('clearAllCache'));
        $this->assertTrue($reflection->hasMethod('clearCacheByPattern'));
        $this->assertTrue($reflection->hasMethod('clearPricesCache'));
        $this->assertTrue($reflection->hasMethod('clearCachePattern'));
        
        // Check that methods are private
        $this->assertTrue($reflection->getMethod('clearAllCache')->isPrivate());
        $this->assertTrue($reflection->getMethod('clearCacheByPattern')->isPrivate());
        $this->assertTrue($reflection->getMethod('clearPricesCache')->isPrivate());
        $this->assertTrue($reflection->getMethod('clearCachePattern')->isPrivate());
    }

    /**
     * Test that command signature format is correct
     */
    public function test_command_signature_format(): void
    {
        $command = new ClearPricesCacheCommand();
        
        $expectedSignature = 'subscription:clear-prices-cache {--all : Clear all subscription-related cache} {--pattern= : Clear cache matching specific pattern}';
        
        // Get the actual signature from the command
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $actualSignature = $property->getValue($command);
        
        $this->assertStringContainsString('subscription:clear-prices-cache', $actualSignature);
        $this->assertStringContainsString('--all', $actualSignature);
        $this->assertStringContainsString('--pattern=', $actualSignature);
    }
}
