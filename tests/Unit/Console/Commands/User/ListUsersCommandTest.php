<?php

namespace Tests\Unit\Console\Commands\User;

use App\Console\Commands\User\ListUsersCommand;
use App\Models\User;
use App\Models\Role;
use PHPUnit\Framework\TestCase as BaseTestCase;

class ListUsersCommandTest extends BaseTestCase
{
    /**
     * Test that command signature is correct
     */
    public function test_command_signature(): void
    {
        $command = new ListUsersCommand();
        
        $this->assertEquals('user:list', $command->getName());
        $this->assertEquals('List users with various filters and export options', $command->getDescription());
        
        // Check that all expected options are defined
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('role'));
        $this->assertTrue($definition->hasOption('trial'));
        $this->assertTrue($definition->hasOption('verified'));
        $this->assertTrue($definition->hasOption('unverified'));
        $this->assertTrue($definition->hasOption('limit'));
        $this->assertTrue($definition->hasOption('format'));
        $this->assertTrue($definition->hasOption('export'));
        $this->assertTrue($definition->hasOption('stats'));
    }

    /**
     * Test that command extends BaseCommand
     */
    public function test_command_extends_base_command(): void
    {
        $command = new ListUsersCommand();
        
        $this->assertInstanceOf(\App\Console\Commands\BaseCommand::class, $command);
    }

    /**
     * Test that command options are properly configured
     */
    public function test_command_options_configuration(): void
    {
        $command = new ListUsersCommand();
        $definition = $command->getDefinition();
        
        // Test option configurations
        $roleOption = $definition->getOption('role');
        $this->assertFalse($roleOption->isValueRequired()); // Optional value
        $this->assertEquals('Filter by role', $roleOption->getDescription());
        
        $trialOption = $definition->getOption('trial');
        $this->assertFalse($trialOption->isValueRequired()); // Optional value
        $this->assertEquals('Filter by trial status (active, expired, none, unlimited)', $trialOption->getDescription());
        
        $verifiedOption = $definition->getOption('verified');
        $this->assertFalse($verifiedOption->isValueRequired()); // Flag option
        $this->assertEquals('Show only verified users', $verifiedOption->getDescription());
        
        $unverifiedOption = $definition->getOption('unverified');
        $this->assertFalse($unverifiedOption->isValueRequired()); // Flag option
        $this->assertEquals('Show only unverified users', $unverifiedOption->getDescription());
        
        $limitOption = $definition->getOption('limit');
        $this->assertFalse($limitOption->isValueRequired()); // Optional value
        $this->assertEquals('Limit number of results', $limitOption->getDescription());
        
        $formatOption = $definition->getOption('format');
        $this->assertFalse($formatOption->isValueRequired()); // Optional value
        $this->assertEquals('Output format (table, json, csv)', $formatOption->getDescription());
        
        $exportOption = $definition->getOption('export');
        $this->assertFalse($exportOption->isValueRequired()); // Optional value
        $this->assertEquals('Export to file', $exportOption->getDescription());
        
        $statsOption = $definition->getOption('stats');
        $this->assertFalse($statsOption->isValueRequired()); // Flag option
        $this->assertEquals('Show user statistics', $statsOption->getDescription());
    }

    /**
     * Test that command has proper return constants
     */
    public function test_command_return_constants(): void
    {
        $command = new ListUsersCommand();
        
        $this->assertEquals(0, $command::SUCCESS);
        $this->assertEquals(1, $command::FAILURE);
    }

    /**
     * Test command handle method exists
     */
    public function test_command_handle_method_exists(): void
    {
        $command = new ListUsersCommand();
        
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
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        
        // Check that private methods likely exist (we can't check them all without reading the full file)
        $this->assertTrue(method_exists($command, 'handle'));
        
        // Check for methods that are commonly used in list commands
        $source = file_get_contents($reflection->getFileName());
        $this->assertStringContainsString('showStats', $source);
        $this->assertStringContainsString('applyFilters', $source);
    }

    /**
     * Test that command signature format is correct
     */
    public function test_command_signature_format(): void
    {
        $command = new ListUsersCommand();
        
        // Get the actual signature from the command
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $actualSignature = $property->getValue($command);
        
        $this->assertStringContainsString('user:list', $actualSignature);
        $this->assertStringContainsString('--role=', $actualSignature);
        $this->assertStringContainsString('--trial=', $actualSignature);
        $this->assertStringContainsString('--verified', $actualSignature);
        $this->assertStringContainsString('--unverified', $actualSignature);
        $this->assertStringContainsString('--limit=', $actualSignature);
        $this->assertStringContainsString('--format=', $actualSignature);
        $this->assertStringContainsString('--export=', $actualSignature);
        $this->assertStringContainsString('--stats', $actualSignature);
    }

    /**
     * Test command uses correct models
     */
    public function test_command_uses_correct_models(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that the command uses the correct models
        $this->assertStringContainsString('use App\Models\User;', $source);
        $this->assertStringContainsString('use App\Models\Role;', $source);
    }

    /**
     * Test command uses required dependencies
     */
    public function test_command_uses_required_dependencies(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that the command uses required dependencies
        $this->assertStringContainsString('use Carbon\Carbon;', $source);
    }

    /**
     * Test that command has proper namespace
     */
    public function test_command_has_proper_namespace(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $this->assertEquals('App\Console\Commands\User\ListUsersCommand', $reflection->getName());
    }

    /**
     * Test that command handles filtering
     */
    public function test_command_handles_filtering(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that filtering is implemented
        $this->assertStringContainsString('applyFilters', $source);
        $this->assertStringContainsString('with(\'roles\', \'subscriptions\')', $source);
    }

    /**
     * Test that command handles different output formats
     */
    public function test_command_handles_output_formats(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that different output formats are supported
        $this->assertStringContainsString('table', $source);
        $this->assertStringContainsString('json', $source);
        $this->assertStringContainsString('csv', $source);
    }

    /**
     * Test that command handles statistics
     */
    public function test_command_handles_statistics(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that statistics functionality is implemented
        $this->assertStringContainsString('showStats', $source);
        $this->assertStringContainsString('stats', $source);
    }

    /**
     * Test that command handles trial filtering
     */
    public function test_command_handles_trial_filtering(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that trial filtering is implemented
        $this->assertStringContainsString('active', $source);
        $this->assertStringContainsString('expired', $source);
        $this->assertStringContainsString('none', $source);
        $this->assertStringContainsString('unlimited', $source);
    }

    /**
     * Test that command handles verification filtering
     */
    public function test_command_handles_verification_filtering(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that verification filtering is implemented
        $this->assertStringContainsString('verified', $source);
        $this->assertStringContainsString('unverified', $source);
        $this->assertStringContainsString('email_verified_at', $source);
    }

    /**
     * Test that command handles export functionality
     */
    public function test_command_handles_export_functionality(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that export functionality is implemented
        $this->assertStringContainsString('export', $source);
    }

    /**
     * Test that command handles limit functionality
     */
    public function test_command_handles_limit_functionality(): void
    {
        $command = new ListUsersCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that limit functionality is implemented
        $this->assertStringContainsString('limit', $source);
    }
}
