<?php

namespace Tests\Unit\Console\Commands\Subscription;

use App\Console\Commands\Subscription\CheckExpiredTrialsCommand;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;

class CheckExpiredTrialsCommandTest extends BaseTestCase
{
    /**
     * Test that command signature is correct
     */
    public function test_command_signature(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        $this->assertEquals('subscription:check-expired-trials', $command->getName());
        $this->assertEquals('Check for expired trials and downgrade users to free if they don\'t have active paid subscriptions', $command->getDescription());
        
        // Check that all expected options are defined
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('user'));
    }

    /**
     * Test that command extends BaseCommand
     */
    public function test_command_extends_base_command(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        $this->assertInstanceOf(\App\Console\Commands\BaseCommand::class, $command);
    }

    /**
     * Test that command options are properly configured
     */
    public function test_command_options_configuration(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        $definition = $command->getDefinition();
        
        // Test option configurations
        $dryRunOption = $definition->getOption('dry-run');
        $this->assertFalse($dryRunOption->isValueRequired()); // Flag option
        $this->assertEquals('Show what would be done without making changes', $dryRunOption->getDescription());
        
        $userOption = $definition->getOption('user');
        $this->assertFalse($userOption->isValueRequired()); // Optional value
        $this->assertEquals('Check specific user UUID only', $userOption->getDescription());
    }

    /**
     * Test that command has proper return constants
     */
    public function test_command_return_constants(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        $this->assertEquals(0, $command::SUCCESS);
        $this->assertEquals(1, $command::FAILURE);
    }

    /**
     * Test command constructor dependencies
     */
    public function test_command_constructor_dependencies(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        $this->assertInstanceOf(CheckExpiredTrialsCommand::class, $command);
    }

    /**
     * Test command handle method exists
     */
    public function test_command_handle_method_exists(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        $this->assertTrue(method_exists($command, 'handle'));
        
        $reflection = new \ReflectionMethod($command, 'handle');
        $returnType = $reflection->getReturnType();
        
        $this->assertEquals('int', $returnType->getName());
    }

    /**
     * Test that private methods exist for different operations
     */
    public function test_private_methods_exist(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        $reflection = new \ReflectionClass($command);
        
        // Check that private methods exist
        $this->assertTrue($reflection->hasMethod('getExpiredTrialUsers'));
        $this->assertTrue($reflection->hasMethod('processExpiredTrials'));
        $this->assertTrue($reflection->hasMethod('processUser'));
        $this->assertTrue($reflection->hasMethod('displaySummary'));
        
        // Check that methods are private
        $this->assertTrue($reflection->getMethod('getExpiredTrialUsers')->isPrivate());
        $this->assertTrue($reflection->getMethod('processExpiredTrials')->isPrivate());
        $this->assertTrue($reflection->getMethod('processUser')->isPrivate());
        $this->assertTrue($reflection->getMethod('displaySummary')->isPrivate());
    }

    /**
     * Test that command signature format is correct
     */
    public function test_command_signature_format(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        // Get the actual signature from the command
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $actualSignature = $property->getValue($command);
        
        $this->assertStringContainsString('subscription:check-expired-trials', $actualSignature);
        $this->assertStringContainsString('--dry-run', $actualSignature);
        $this->assertStringContainsString('--user=', $actualSignature);
    }

    /**
     * Test command has proper service dependencies
     */
    public function test_command_service_dependencies(): void
    {
        $subscriptionService = $this->createMock(SubscriptionService::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $command = new CheckExpiredTrialsCommand($subscriptionService, $userRepository);
        
        $reflection = new \ReflectionClass($command);
        
        // Check that constructor parameters are properly typed
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();
        
        $this->assertEquals(2, count($parameters));
        $this->assertEquals('subscriptionService', $parameters[0]->getName());
        $this->assertEquals('userRepository', $parameters[1]->getName());
        
        $this->assertEquals(SubscriptionService::class, $parameters[0]->getType()->getName());
        $this->assertEquals(UserRepositoryInterface::class, $parameters[1]->getType()->getName());
    }
}
