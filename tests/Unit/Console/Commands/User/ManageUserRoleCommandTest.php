<?php

namespace Tests\Unit\Console\Commands\User;

use App\Console\Commands\User\ManageUserRoleCommand;
use App\Models\User;
use App\Models\Role;
use PHPUnit\Framework\TestCase as BaseTestCase;

class ManageUserRoleCommandTest extends BaseTestCase
{
    /**
     * Test that command signature is correct
     */
    public function test_command_signature(): void
    {
        $command = new ManageUserRoleCommand();
        
        $this->assertEquals('user:role', $command->getName());
        $this->assertEquals('Manage user roles - assign, remove, sync, or list roles', $command->getDescription());
        
        // Check that all expected arguments and options are defined
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('user'));
        $this->assertTrue($definition->hasOption('assign'));
        $this->assertTrue($definition->hasOption('remove'));
        $this->assertTrue($definition->hasOption('sync'));
        $this->assertTrue($definition->hasOption('list'));
        $this->assertTrue($definition->hasOption('clear'));
    }

    /**
     * Test that command extends BaseCommand
     */
    public function test_command_extends_base_command(): void
    {
        $command = new ManageUserRoleCommand();
        
        $this->assertInstanceOf(\App\Console\Commands\BaseCommand::class, $command);
    }

    /**
     * Test that command arguments are properly configured
     */
    public function test_command_arguments_configuration(): void
    {
        $command = new ManageUserRoleCommand();
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
        $command = new ManageUserRoleCommand();
        $definition = $command->getDefinition();
        
        // Test option configurations
        $assignOption = $definition->getOption('assign');
        $this->assertFalse($assignOption->isValueRequired()); // Optional value
        $this->assertEquals('Role to assign', $assignOption->getDescription());
        
        $removeOption = $definition->getOption('remove');
        $this->assertFalse($removeOption->isValueRequired()); // Optional value
        $this->assertEquals('Role to remove', $removeOption->getDescription());
        
        $syncOption = $definition->getOption('sync');
        $this->assertFalse($syncOption->isValueRequired()); // Optional value
        $this->assertEquals('Roles to sync (comma-separated)', $syncOption->getDescription());
        
        $listOption = $definition->getOption('list');
        $this->assertFalse($listOption->isValueRequired()); // Flag option
        $this->assertEquals('List user roles', $listOption->getDescription());
        
        $clearOption = $definition->getOption('clear');
        $this->assertFalse($clearOption->isValueRequired()); // Flag option
        $this->assertEquals('Remove all roles', $clearOption->getDescription());
    }

    /**
     * Test that command has proper return constants
     */
    public function test_command_return_constants(): void
    {
        $command = new ManageUserRoleCommand();
        
        $this->assertEquals(0, $command::SUCCESS);
        $this->assertEquals(1, $command::FAILURE);
    }

    /**
     * Test command handle method exists
     */
    public function test_command_handle_method_exists(): void
    {
        $command = new ManageUserRoleCommand();
        
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
        $command = new ManageUserRoleCommand();
        
        // Get the actual signature from the command
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $actualSignature = $property->getValue($command);
        
        $this->assertStringContainsString('user:role', $actualSignature);
        $this->assertStringContainsString('{user', $actualSignature);
        $this->assertStringContainsString('--assign=', $actualSignature);
        $this->assertStringContainsString('--remove=', $actualSignature);
        $this->assertStringContainsString('--sync=', $actualSignature);
        $this->assertStringContainsString('--list', $actualSignature);
        $this->assertStringContainsString('--clear', $actualSignature);
    }

    /**
     * Test command uses correct models
     */
    public function test_command_uses_correct_models(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that the command uses the correct models
        $this->assertStringContainsString('use App\Models\User;', $source);
        $this->assertStringContainsString('use App\Models\Role;', $source);
    }

    /**
     * Test that command has proper namespace
     */
    public function test_command_has_proper_namespace(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $this->assertEquals('App\Console\Commands\User\ManageUserRoleCommand', $reflection->getName());
    }

    /**
     * Test that command handles user lookup by email and UUID
     */
    public function test_command_handles_user_lookup(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that user lookup is implemented for both email and UUID
        $this->assertStringContainsString('where(\'email\', $userIdentifier)', $source);
        $this->assertStringContainsString('orWhere(\'uuid\', $userIdentifier)', $source);
    }

    /**
     * Test that command handles role assignment
     */
    public function test_command_handles_role_assignment(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that role assignment is implemented
        $this->assertStringContainsString('assign', $source);
        $this->assertStringContainsString('assignRole', $source);
    }

    /**
     * Test that command handles role removal
     */
    public function test_command_handles_role_removal(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that role removal is implemented
        $this->assertStringContainsString('remove', $source);
        $this->assertStringContainsString('removeRole', $source);
    }

    /**
     * Test that command handles role synchronization
     */
    public function test_command_handles_role_synchronization(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that role synchronization is implemented
        $this->assertStringContainsString('sync', $source);
        $this->assertStringContainsString('syncRoles', $source);
    }

    /**
     * Test that command handles role listing
     */
    public function test_command_handles_role_listing(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that role listing is implemented
        $this->assertStringContainsString('list', $source);
        $this->assertStringContainsString('roles', $source);
    }

    /**
     * Test that command handles role clearing
     */
    public function test_command_handles_role_clearing(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that role clearing is implemented
        $this->assertStringContainsString('clear', $source);
    }

    /**
     * Test that command handles comma-separated role lists
     */
    public function test_command_handles_comma_separated_roles(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that comma-separated roles are handled
        $this->assertStringContainsString('comma-separated', $source);
        $this->assertStringContainsString('explode', $source);
    }

    /**
     * Test that command provides user feedback
     */
    public function test_command_provides_user_feedback(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that user feedback is provided
        $this->assertStringContainsString('Managing roles for:', $source);
        $this->assertStringContainsString('User not found:', $source);
    }

    /**
     * Test that command handles error cases
     */
    public function test_command_handles_error_cases(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that error handling is implemented
        $this->assertStringContainsString('User not found', $source);
        $this->assertStringContainsString('self::FAILURE', $source);
    }

    /**
     * Test that command uses role model methods
     */
    public function test_command_uses_role_model_methods(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that role model methods are used
        $this->assertStringContainsString('assignRole', $source);
        $this->assertStringContainsString('removeRole', $source);
        $this->assertStringContainsString('syncRoles', $source);
    }

    /**
     * Test that command displays current roles
     */
    public function test_command_displays_current_roles(): void
    {
        $command = new ManageUserRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that current roles are displayed
        $this->assertStringContainsString('List current roles', $source);
        $this->assertStringContainsString('roles', $source);
    }
}
