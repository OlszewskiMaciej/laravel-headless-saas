<?php

namespace Tests\Unit\Console\Commands\User;

use App\Console\Commands\User\CreateUserCommand;
use App\Models\User;
use App\Models\Role;
use PHPUnit\Framework\TestCase as BaseTestCase;

class CreateUserCommandTest extends BaseTestCase
{
    /**
     * Test that command signature is correct
     */
    public function test_command_signature(): void
    {
        $command = new CreateUserCommand();
        
        $this->assertEquals('user:create', $command->getName());
        $this->assertEquals('Create a new user with optional role assignment and trial configuration', $command->getDescription());
        
        // Check that all expected options are defined
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('name'));
        $this->assertTrue($definition->hasOption('email'));
        $this->assertTrue($definition->hasOption('password'));
        $this->assertTrue($definition->hasOption('role'));
        $this->assertTrue($definition->hasOption('admin'));
        $this->assertTrue($definition->hasOption('trial-days'));
        $this->assertTrue($definition->hasOption('unlimited-trial'));
        $this->assertTrue($definition->hasOption('no-trial'));
    }

    /**
     * Test that command extends BaseCommand
     */
    public function test_command_extends_base_command(): void
    {
        $command = new CreateUserCommand();
        
        $this->assertInstanceOf(\App\Console\Commands\BaseCommand::class, $command);
    }

    /**
     * Test that command options are properly configured
     */
    public function test_command_options_configuration(): void
    {
        $command = new CreateUserCommand();
        $definition = $command->getDefinition();
        
        // Test option configurations
        $nameOption = $definition->getOption('name');
        $this->assertFalse($nameOption->isValueRequired()); // Optional value
        $this->assertEquals('The name of the user', $nameOption->getDescription());
        
        $emailOption = $definition->getOption('email');
        $this->assertFalse($emailOption->isValueRequired()); // Optional value
        $this->assertEquals('The email of the user', $emailOption->getDescription());
        
        $passwordOption = $definition->getOption('password');
        $this->assertFalse($passwordOption->isValueRequired()); // Optional value
        $this->assertEquals('The password for the user', $passwordOption->getDescription());
        
        $roleOption = $definition->getOption('role');
        $this->assertFalse($roleOption->isValueRequired()); // Optional value
        $this->assertEquals('The role to assign to the user', $roleOption->getDescription());
        
        $adminOption = $definition->getOption('admin');
        $this->assertFalse($adminOption->isValueRequired()); // Flag option
        $this->assertEquals('Create an admin user', $adminOption->getDescription());
        
        $trialDaysOption = $definition->getOption('trial-days');
        $this->assertFalse($trialDaysOption->isValueRequired()); // Optional value
        $this->assertEquals('Number of trial days (default: 30)', $trialDaysOption->getDescription());
        
        $unlimitedTrialOption = $definition->getOption('unlimited-trial');
        $this->assertFalse($unlimitedTrialOption->isValueRequired()); // Flag option
        $this->assertEquals('Give unlimited trial access', $unlimitedTrialOption->getDescription());
        
        $noTrialOption = $definition->getOption('no-trial');
        $this->assertFalse($noTrialOption->isValueRequired()); // Flag option
        $this->assertEquals('Create user without trial', $noTrialOption->getDescription());
    }

    /**
     * Test that command has proper return constants
     */
    public function test_command_return_constants(): void
    {
        $command = new CreateUserCommand();
        
        $this->assertEquals(0, $command::SUCCESS);
        $this->assertEquals(1, $command::FAILURE);
    }

    /**
     * Test command handle method exists
     */
    public function test_command_handle_method_exists(): void
    {
        $command = new CreateUserCommand();
        
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
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        
        // Check that private methods exist
        $this->assertTrue($reflection->hasMethod('assignRole'));
        
        // Check that methods are private
        $this->assertTrue($reflection->getMethod('assignRole')->isPrivate());
    }

    /**
     * Test that command signature format is correct
     */
    public function test_command_signature_format(): void
    {
        $command = new CreateUserCommand();
        
        // Get the actual signature from the command
        $reflection = new \ReflectionClass($command);
        $property = $reflection->getProperty('signature');
        $property->setAccessible(true);
        $actualSignature = $property->getValue($command);
        
        $this->assertStringContainsString('user:create', $actualSignature);
        $this->assertStringContainsString('--name=', $actualSignature);
        $this->assertStringContainsString('--email=', $actualSignature);
        $this->assertStringContainsString('--password=', $actualSignature);
        $this->assertStringContainsString('--role=', $actualSignature);
        $this->assertStringContainsString('--admin', $actualSignature);
        $this->assertStringContainsString('--trial-days=', $actualSignature);
        $this->assertStringContainsString('--unlimited-trial', $actualSignature);
        $this->assertStringContainsString('--no-trial', $actualSignature);
    }

    /**
     * Test that assignRole method signature is correct
     */
    public function test_assign_role_method_signature(): void
    {
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('assignRole');
        $parameters = $method->getParameters();
        
        $this->assertEquals(2, count($parameters));
        $this->assertEquals('user', $parameters[0]->getName());
        $this->assertEquals('roleName', $parameters[1]->getName());
        
        $this->assertEquals(User::class, $parameters[0]->getType()->getName());
        $this->assertEquals('string', $parameters[1]->getType()->getName());
        
        $returnType = $method->getReturnType();
        $this->assertEquals('void', $returnType->getName());
    }

    /**
     * Test command uses correct models
     */
    public function test_command_uses_correct_models(): void
    {
        $command = new CreateUserCommand();
        
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
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that the command uses required dependencies
        $this->assertStringContainsString('use Carbon\Carbon;', $source);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Hash;', $source);
        $this->assertStringContainsString('use Illuminate\Support\Facades\Validator;', $source);
    }

    /**
     * Test that command has proper namespace
     */
    public function test_command_has_proper_namespace(): void
    {
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        $this->assertEquals('App\Console\Commands\User\CreateUserCommand', $reflection->getName());
    }

    /**
     * Test that command handles validation properly
     */
    public function test_command_handles_validation(): void
    {
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that validation is implemented
        $this->assertStringContainsString('Validator::make', $source);
        $this->assertStringContainsString('required|string|max:255', $source);
        $this->assertStringContainsString('required|email|unique:users,email', $source);
        $this->assertStringContainsString('required|min:8', $source);
    }

    /**
     * Test that command handles trial configuration
     */
    public function test_command_handles_trial_configuration(): void
    {
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that trial configuration is implemented
        $this->assertStringContainsString('unlimited-trial', $source);
        $this->assertStringContainsString('no-trial', $source);
        $this->assertStringContainsString('trial-days', $source);
        $this->assertStringContainsString('trial_ends_at', $source);
        $this->assertStringContainsString('Carbon::now()->addDays', $source);
    }

    /**
     * Test that command handles password hashing
     */
    public function test_command_handles_password_hashing(): void
    {
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that password hashing is implemented
        $this->assertStringContainsString('Hash::make', $source);
    }

    /**
     * Test that command handles email verification
     */
    public function test_command_handles_email_verification(): void
    {
        $command = new CreateUserCommand();
        
        $reflection = new \ReflectionClass($command);
        $source = file_get_contents($reflection->getFileName());
        
        // Check that email verification is set
        $this->assertStringContainsString('email_verified_at', $source);
        $this->assertStringContainsString('Carbon::now()', $source);
    }
}
