<?php

namespace Tests\Unit\Console\Commands\Role;

use App\Console\Commands\Role\ManageRoleCommand;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use PHPUnit\Framework\TestCase as BaseTestCase;

class ManageRoleCommandTest extends BaseTestCase
{
    /**
     * Test that command signature is correct
     */
    public function test_command_signature(): void
    {
        $command = new ManageRoleCommand();
        
        $this->assertEquals('role:manage', $command->getName());
        $this->assertEquals('Manage roles and permissions - create, delete, list, and assign permissions', $command->getDescription());
        
        // Check that all expected arguments and options are defined
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('action'));
        $this->assertTrue($definition->hasArgument('role'));
        $this->assertTrue($definition->hasOption('permissions'));
        $this->assertTrue($definition->hasOption('force'));
    }

    /**
     * Test that command extends BaseCommand
     */
    public function test_command_extends_base_command(): void
    {
        $command = new ManageRoleCommand();
        
        $this->assertInstanceOf(\App\Console\Commands\BaseCommand::class, $command);
    }

    /**
     * Test that command arguments are properly configured
     */
    public function test_command_arguments_configuration(): void
    {
        $command = new ManageRoleCommand();
        $definition = $command->getDefinition();
        
        // Test argument configurations
        $actionArgument = $definition->getArgument('action');
        $this->assertTrue($actionArgument->isRequired());
        $this->assertEquals('The action to perform (create, delete, list, permissions)', $actionArgument->getDescription());
        
        $roleArgument = $definition->getArgument('role');
        $this->assertFalse($roleArgument->isRequired());
        $this->assertEquals('The role name', $roleArgument->getDescription());
    }

    /**
     * Test that command options are properly configured
     */
    public function test_command_options_configuration(): void
    {
        $command = new ManageRoleCommand();
        $definition = $command->getDefinition();
        
        // Test option configurations
        $permissionsOption = $definition->getOption('permissions');
        $this->assertFalse($permissionsOption->isValueRequired()); // Optional value
        $this->assertEquals('Comma-separated list of permissions', $permissionsOption->getDescription());
        
        $forceOption = $definition->getOption('force');
        $this->assertFalse($forceOption->isValueRequired()); // Flag option
        $this->assertEquals('Force the action without confirmation', $forceOption->getDescription());
    }

    /**
     * Test that command has proper return constants
     */
    public function test_command_return_constants(): void
    {
        $command = new ManageRoleCommand();
        
        $this->assertEquals(0, $command::SUCCESS);
        $this->assertEquals(1, $command::FAILURE);
    }

    /**
     * Test command handle method exists
     */
    public function test_command_handle_method_exists(): void
    {
        $command = new ManageRoleCommand();
        
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
        $command = new ManageRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        
        // Check that private methods exist
        $this->assertTrue($reflection->hasMethod('createRole'));
        $this->assertTrue($reflection->hasMethod('deleteRole'));
        $this->assertTrue($reflection->hasMethod('listRoles'));
        $this->assertTrue($reflection->hasMethod('managePermissions'));
        $this->assertTrue($reflection->hasMethod('assignPermissions'));
        $this->assertTrue($reflection->hasMethod('manageRolePermissions'));
        
        // Check that methods are private
        $this->assertTrue($reflection->getMethod('createRole')->isPrivate());
        $this->assertTrue($reflection->getMethod('deleteRole')->isPrivate());
        $this->assertTrue($reflection->getMethod('listRoles')->isPrivate());
        $this->assertTrue($reflection->getMethod('managePermissions')->isPrivate());
        $this->assertTrue($reflection->getMethod('assignPermissions')->isPrivate());
        $this->assertTrue($reflection->getMethod('manageRolePermissions')->isPrivate());
    }

    /**
     * Test that command signature format is correct
     */
    public function test_command_signature_format(): void
    {
        $command = new ManageRoleCommand();
        
        // Get the actual signature from the command
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $actualSignature = $property->getValue($command);
        
        $this->assertStringContainsString('role:manage', $actualSignature);
        $this->assertStringContainsString('{action', $actualSignature);
        $this->assertStringContainsString('{role?', $actualSignature);
        $this->assertStringContainsString('--permissions=', $actualSignature);
        $this->assertStringContainsString('--force', $actualSignature);
    }

    /**
     * Test that handle method supports all required actions
     */
    public function test_handle_method_supports_all_actions(): void
    {
        $command = new ManageRoleCommand();
        
        // Check handle method implementation references all actions
        $reflection = new \ReflectionMethod($command, 'handle');
        $reflection->setAccessible(true);
        
        // We can't directly test the switch statement, but we can verify the methods exist
        $this->assertTrue(method_exists($command, 'createRole'));
        $this->assertTrue(method_exists($command, 'deleteRole'));
        $this->assertTrue(method_exists($command, 'listRoles'));
        $this->assertTrue(method_exists($command, 'managePermissions'));
    }

    /**
     * Test that assignPermissions method signature is correct
     */
    public function test_assign_permissions_method_signature(): void
    {
        $command = new ManageRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('assignPermissions');
        $parameters = $method->getParameters();
        
        $this->assertEquals(2, count($parameters));
        $this->assertEquals('role', $parameters[0]->getName());
        $this->assertEquals('permissionsString', $parameters[1]->getName());
        
        $this->assertEquals(Role::class, $parameters[0]->getType()->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());
        
        $returnType = $method->getReturnType();
        $this->assertEquals('int', $returnType->getName());
    }

    /**
     * Test that manageRolePermissions method signature is correct
     */
    public function test_manage_role_permissions_method_signature(): void
    {
        $command = new ManageRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('manageRolePermissions');
        $parameters = $method->getParameters();
        
        $this->assertEquals(1, count($parameters));
        $this->assertEquals('role', $parameters[0]->getName());
        $this->assertEquals(Role::class, $parameters[0]->getType()->getName());
        
        $returnType = $method->getReturnType();
        $this->assertEquals('int', $returnType->getName());
    }

    /**
     * Test command uses correct models
     */
    public function test_command_uses_correct_models(): void
    {
        $command = new ManageRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that the command uses the correct models
        $this->assertStringContainsString('use App\Models\Role;', $source);
        $this->assertStringContainsString('use App\Models\Permission;', $source);
        $this->assertStringContainsString('use App\Models\User;', $source);
    }

    /**
     * Test that command has proper namespace
     */
    public function test_command_has_proper_namespace(): void
    {
        $command = new ManageRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        $this->assertEquals('App\Console\Commands\Role\ManageRoleCommand', $reflection->getName());
    }

    /**
     * Test that command methods return proper types
     */
    public function test_command_methods_return_types(): void
    {
        $command = new ManageRoleCommand();
        
        $reflection = new \ReflectionClass($command);
        
        // Check return types for all private methods
        $methods = ['createRole', 'deleteRole', 'listRoles', 'managePermissions', 'assignPermissions', 'manageRolePermissions'];
        
        foreach ($methods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $returnType = $method->getReturnType();
            
            $this->assertNotNull($returnType, "Method {$methodName} should have a return type");
            $this->assertEquals('int', $returnType->getName(), "Method {$methodName} should return int");
        }
    }
}
