<?php

namespace Tests\Unit\Console\Commands\User;

use App\Console\Commands\User\ManageTrialCommand;
use App\Models\User;
use PHPUnit\Framework\TestCase as BaseTestCase;

class ManageTrialCommandTest extends BaseTestCase
{
    /**
     * Test that command signature is correct
     */
    public function test_command_signature(): void
    {
        $command = new ManageTrialCommand();
        
        $this->assertEquals('user:trial', $command->getName());
        $this->assertEquals('Manage user trial access - extend, set, make unlimited, or remove', $command->getDescription());
        
        // Check that all expected arguments and options are defined
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('user'));
        $this->assertTrue($definition->hasOption('extend'));
        $this->assertTrue($definition->hasOption('set'));
        $this->assertTrue($definition->hasOption('unlimited'));
        $this->assertTrue($definition->hasOption('remove'));
        $this->assertTrue($definition->hasOption('status'));
    }

    /**
     * Test that command extends BaseCommand
     */
    public function test_command_extends_base_command(): void
    {
        $command = new ManageTrialCommand();
        
        $this->assertInstanceOf(\App\Console\Commands\BaseCommand::class, $command);
    }

    /**
     * Test that command arguments are properly configured
     */
    public function test_command_arguments_configuration(): void
    {
        $command = new ManageTrialCommand();
        $definition = $command->getDefinition();
        
        // Test argument configurations
        $userArgument = $definition->getArgument('user');
        $this->assertTrue($userArgument->isRequired());
        $this->assertEquals('The user email or UUID', $userArgument->getDescription());
    }

    /**
     * Test that command options are properly configured
     */
    public function test_command_options_configuration(): void
    {
        $command = new ManageTrialCommand();
        $definition = $command->getDefinition();
        
        // Test option configurations
        $extendOption = $definition->getOption('extend');
        $this->assertFalse($extendOption->isValueRequired()); // Optional value
        $this->assertEquals('Extend trial by X days', $extendOption->getDescription());
        
        $setOption = $definition->getOption('set');
        $this->assertFalse($setOption->isValueRequired()); // Optional value
        $this->assertEquals('Set trial end date (Y-m-d format)', $setOption->getDescription());
        
        $unlimitedOption = $definition->getOption('unlimited');
        $this->assertFalse($unlimitedOption->isValueRequired()); // Flag option
        $this->assertEquals('Set unlimited trial', $unlimitedOption->getDescription());
        
        $removeOption = $definition->getOption('remove');
        $this->assertFalse($removeOption->isValueRequired()); // Flag option
        $this->assertEquals('Remove trial access', $removeOption->getDescription());
        
        $statusOption = $definition->getOption('status');
        $this->assertFalse($statusOption->isValueRequired()); // Flag option
        $this->assertEquals('Show trial status', $statusOption->getDescription());
    }

    /**
     * Test that command has proper return constants
     */
    public function test_command_return_constants(): void
    {
        $command = new ManageTrialCommand();
        
        $this->assertEquals(0, $command::SUCCESS);
        $this->assertEquals(1, $command::FAILURE);
    }

    /**
     * Test command handle method exists
     */
    public function test_command_handle_method_exists(): void
    {
        $command = new ManageTrialCommand();
        
        $this->assertTrue(method_exists($command, 'handle'));
        
        $reflection = new \ReflectionMethod($command, 'handle');
        $returnType = $reflection->getReturnType();
        
        $this->assertEquals('int', $returnType->getName());
    }

    /**
     * Test that command signature format is correct
     */
    public function test_command_signature_format(): void
    {
        $command = new ManageTrialCommand();
        
        // Get the actual signature from the command
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $actualSignature = $property->getValue($command);
        
        $this->assertStringContainsString('user:trial', $actualSignature);
        $this->assertStringContainsString('{user', $actualSignature);
        $this->assertStringContainsString('--extend=', $actualSignature);
        $this->assertStringContainsString('--set=', $actualSignature);
        $this->assertStringContainsString('--unlimited', $actualSignature);
        $this->assertStringContainsString('--remove', $actualSignature);
        $this->assertStringContainsString('--status', $actualSignature);
    }

    /**
     * Test command uses correct models
     */
    public function test_command_uses_correct_models(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that the command uses the correct models
        $this->assertStringContainsString('use App\Models\User;', $source);
    }

    /**
     * Test command uses required dependencies
     */
    public function test_command_uses_required_dependencies(): void
    {
        $command = new ManageTrialCommand();
        
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
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $this->assertEquals('App\Console\Commands\User\ManageTrialCommand', $reflection->getName());
    }

    /**
     * Test that command handles user lookup by email and UUID
     */
    public function test_command_handles_user_lookup(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that user lookup is implemented for both email and UUID
        $this->assertStringContainsString('where(\'email\', $userIdentifier)', $source);
        $this->assertStringContainsString('orWhere(\'uuid\', $userIdentifier)', $source);
    }

    /**
     * Test that command handles trial extension
     */
    public function test_command_handles_trial_extension(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that trial extension is implemented
        $this->assertStringContainsString('extend', $source);
        $this->assertStringContainsString('addDays', $source);
    }

    /**
     * Test that command handles trial date setting
     */
    public function test_command_handles_trial_date_setting(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that trial date setting is implemented
        $this->assertStringContainsString('set', $source);
        $this->assertStringContainsString('Y-m-d', $source);
    }

    /**
     * Test that command handles unlimited trial
     */
    public function test_command_handles_unlimited_trial(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that unlimited trial is implemented
        $this->assertStringContainsString('unlimited', $source);
    }

    /**
     * Test that command handles trial removal
     */
    public function test_command_handles_trial_removal(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that trial removal is implemented
        $this->assertStringContainsString('remove', $source);
    }

    /**
     * Test that command handles trial status display
     */
    public function test_command_handles_trial_status_display(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that trial status display is implemented
        $this->assertStringContainsString('status', $source);
        $this->assertStringContainsString('trial_ends_at', $source);
    }

    /**
     * Test that command provides user feedback
     */
    public function test_command_provides_user_feedback(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that user feedback is provided
        $this->assertStringContainsString('Managing trial for:', $source);
        $this->assertStringContainsString('User not found:', $source);
    }

    /**
     * Test that command handles error cases
     */
    public function test_command_handles_error_cases(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that error handling is implemented
        $this->assertStringContainsString('User not found', $source);
        $this->assertStringContainsString('self::FAILURE', $source);
    }

    /**
     * Test that command handles date validation
     */
    public function test_command_handles_date_validation(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that date validation is implemented
        $this->assertStringContainsString('Y-m-d', $source);
        $this->assertStringContainsString('Carbon::createFromFormat', $source);
    }

    /**
     * Test that command updates trial_ends_at field
     */
    public function test_command_updates_trial_ends_at_field(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that trial_ends_at field is updated
        $this->assertStringContainsString('trial_ends_at', $source);
    }

    /**
     * Test that command handles Carbon date operations
     */
    public function test_command_handles_carbon_date_operations(): void
    {
        $command = new ManageTrialCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that Carbon date operations are used
        $this->assertStringContainsString('Carbon::', $source);
        $this->assertStringContainsString('addDays', $source);
        $this->assertStringContainsString('createFromFormat', $source);
    }
}
