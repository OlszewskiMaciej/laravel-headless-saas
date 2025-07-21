<?php

namespace Tests\Feature\Console\Commands\Subscription;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncCommandFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test sync command with invalid user ID
     */
    public function test_sync_command_with_invalid_user_id(): void
    {
        $exitCode = Artisan::call('subscription:sync', [
            '--user' => 'invalid-uuid'
        ]);

        $this->assertEquals(1, $exitCode);
    }

    /**
     * Test sync command with no users to sync
     */
    public function test_sync_command_with_no_users(): void
    {
        // Create users without stripe_id
        User::factory()->count(2)->create(['stripe_id' => null]);

        $exitCode = Artisan::call('subscription:sync');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('No users found with Stripe IDs to sync', $output);
    }

    /**
     * Test default option values
     */
    public function test_default_option_values(): void
    {
        $exitCode = Artisan::call('subscription:sync', ['--dry-run' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        
        // Should use default values when no options provided
        $this->assertStringContainsString('Starting subscription synchronization', $output);
    }

    /**
     * Test sync command shows correct user count in output
     */
    public function test_sync_command_shows_user_count(): void
    {
        // Create users with stripe_id
        User::factory()->count(3)->create(['stripe_id' => 'cus_test']);

        $exitCode = Artisan::call('subscription:sync', [
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Found 3 users to sync', $output);
    }

    /**
     * Test sync command with days filter
     */
    public function test_sync_command_with_days_filter(): void
    {
        // Create users with different update times
        User::factory()->create([
            'stripe_id' => 'cus_recent123',
            'updated_at' => Carbon::now()->subHours(12),
            'last_subscription_sync' => Carbon::now()->subHours(12)
        ]);
        
        User::factory()->create([
            'stripe_id' => 'cus_old123',
            'updated_at' => Carbon::now()->subDays(5),
            'last_subscription_sync' => Carbon::now()->subDays(5)
        ]);

        $exitCode = Artisan::call('subscription:sync', [
            '--days' => 1,
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        // With the filter, only the recent user should be found
        $this->assertStringContainsString('Found 1 users to sync', $output);
    }

    /**
     * Test sync command output format
     */
    public function test_sync_command_output_format(): void
    {
        $exitCode = Artisan::call('subscription:sync', [
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        
        // Check for expected output format
        $this->assertStringContainsString('Starting subscription synchronization', $output);
        $this->assertStringContainsString('DRY RUN', $output);
    }

    /**
     * Test sync command with batch size setting
     */
    public function test_sync_command_with_batch_size(): void
    {
        // Create multiple users
        User::factory()->count(5)->create(['stripe_id' => 'cus_test']);

        $exitCode = Artisan::call('subscription:sync', [
            '--batch-size' => 3,
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        
        // Should find all users regardless of batch size
        $this->assertStringContainsString('Found 5 users to sync', $output);
    }

    /**
     * Test sync command progress indication
     */
    public function test_sync_command_progress_indication(): void
    {
        // Create a few users to sync
        User::factory()->count(2)->create(['stripe_id' => 'cus_test']);

        $exitCode = Artisan::call('subscription:sync', [
            '--dry-run' => true
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        
        // Should show progress bar or completion message
        $this->assertStringContainsString('Found 2 users to sync', $output);
    }
}
