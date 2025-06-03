<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Cashier;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // This mocks the Cashier Stripe integration for testing
        $this->mock(Cashier::class, function ($mock) {
            $mock->shouldReceive('stripe')->andReturnSelf();
            $mock->shouldReceive('customers')->andReturnSelf();
            $mock->shouldReceive('create')->andReturn((object) ['id' => 'cus_test']);
            $mock->shouldReceive('createAsStripeCustomer')->andReturn((object) ['id' => 'cus_test']);
        });
    }

    public function test_users_can_start_trial(): void
    {
        $user = User::factory()->create();
        $user->assignRole('free');
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withApiToken($token)
            ->postJson('/api/subscription/start-trial');

        $response->assertStatus(200);
        
        $user->refresh();
        $this->assertNotNull($user->trial_ends_at);
        $this->assertTrue($user->hasRole('trial'));
    }

    public function test_users_cannot_start_trial_twice(): void
    {
        $user = User::factory()->create([
            'trial_ends_at' => Carbon::now()->addDays(5),
        ]);
        $user->assignRole('trial');
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withApiToken($token)
            ->postJson('/api/subscription/start-trial');

        $response->assertStatus(403);
    }

    public function test_users_can_view_subscription_status(): void
    {
        $user = User::factory()->create([
            'trial_ends_at' => Carbon::now()->addDays(5),
        ]);
        $user->assignRole('trial');
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withApiToken($token)
            ->getJson('/api/subscription');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'status',
                    'on_trial',
                    'trial_ends_at'
                ]
            ]);
    }

    public function test_users_cannot_start_trial_after_previous_trial_expired(): void
    {
        // Create a user who had a trial that has now expired
        $user = User::factory()->create([
            'trial_ends_at' => Carbon::now()->subDays(5), // Trial ended 5 days ago
        ]);
        $user->assignRole('free'); // User is back to free role after trial expiration
        $token = $user->createToken('auth_token')->plainTextToken;

        // Try to start a new trial
        $response = $this->withApiToken($token)
            ->postJson('/api/subscription/start-trial');

        // Should be rejected because they've already had a trial
        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'You have already used your trial period'
            ]);
            
        // Make sure the trial_ends_at date wasn't changed
        $user->refresh();
        $this->assertTrue($user->trial_ends_at->lt(Carbon::now()));
        $this->assertTrue($user->hasRole('free')); // Role should remain unchanged
    }    
}
