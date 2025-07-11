<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $subscriptionServiceMock;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->subscriptionServiceMock = $this->mock(SubscriptionService::class);
        
        $this->user = User::factory()->create();
        // Don't assign role here - let each test assign what it needs
    }

    #[Test]
    public function it_can_get_subscription_status()
    {
        $this->user->assignRole('free'); // Give user basic role
        
        $expectedData = [
            'status' => 'active',
            'has_subscription' => true,
            'on_trial' => false,
            'plan' => 'premium'
        ];

        $this->subscriptionServiceMock
            ->shouldReceive('getSubscriptionStatus')
            ->once()
            ->with($this->user)
            ->andReturn($expectedData);

        $response = $this->actingAs($this->user)
            ->getJson('/api/subscription');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => $expectedData
            ]);
    }

    #[Test]
    public function it_handles_subscription_status_error()
    {
        $this->user->assignRole('free'); // Give user basic role
        
        $this->subscriptionServiceMock
            ->shouldReceive('getSubscriptionStatus')
            ->once()
            ->with($this->user)
            ->andThrow(new \Exception('Stripe API error'));

        Log::shouldReceive('error')->once();

        $response = $this->actingAs($this->user)
            ->getJson('/api/subscription');

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to retrieve subscription information'
            ]);
    }

    #[Test]
    public function it_can_start_trial_for_authorized_user()
    {
        $this->user->assignRole('free'); // This role has 'start trial' permission

        $this->subscriptionServiceMock
            ->shouldReceive('startTrial')
            ->once()
            ->with($this->user);

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/start-trial');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Trial started successfully'
            ]);
    }

    #[Test]
    public function it_denies_trial_start_for_unauthorized_user()
    {
        // User has no role/permissions, so should be denied by permission middleware

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/start-trial');

        // The permission middleware should return 403 directly
        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_invalid_trial_start_request()
    {
        $this->user->assignRole('free'); // Give permission to start trial

        $this->subscriptionServiceMock
            ->shouldReceive('startTrial')
            ->once()
            ->with($this->user)
            ->andThrow(new \InvalidArgumentException('User already has active trial'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/start-trial');

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'User already has active trial'
            ]);
    }

    #[Test]
    public function it_handles_trial_start_server_error()
    {
        $this->user->assignRole('free'); // Give permission to start trial

        $this->subscriptionServiceMock
            ->shouldReceive('startTrial')
            ->once()
            ->with($this->user)
            ->andThrow(new \Exception('Database error'));

        Log::shouldReceive('error')->once();

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/start-trial');

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to start trial'
            ]);
    }

    #[Test]
    public function it_can_create_checkout_session_for_authorized_user()
    {
        $this->user->assignRole('free'); // Give user basic role

        $this->subscriptionServiceMock
            ->shouldReceive('getSubscriptionStatus')
            ->once()
            ->with($this->user)
            ->andReturn(['has_subscription' => false]);

        $this->subscriptionServiceMock
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->with($this->user, ['plan' => 'premium'])
            ->andReturn(['url' => 'https://checkout.stripe.com/session123']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/checkout', [
                'plan' => 'premium'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => ['url' => 'https://checkout.stripe.com/session123'],
                'message' => 'Checkout session created successfully'
            ]);
    }

    #[Test]
    public function it_denies_checkout_for_user_with_existing_subscription()
    {
        $this->user->assignRole('free'); // Give user basic role

        $this->subscriptionServiceMock
            ->shouldReceive('getSubscriptionStatus')
            ->once()
            ->with($this->user)
            ->andReturn(['has_subscription' => true]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/checkout', [
                'plan' => 'premium'
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'You already have an active subscription or trial'
            ]);
    }

    #[Test]
    public function it_handles_invalid_checkout_request()
    {
        $this->user->assignRole('free'); // Give user basic role

        $this->subscriptionServiceMock
            ->shouldReceive('getSubscriptionStatus')
            ->once()
            ->with($this->user)
            ->andReturn(['has_subscription' => false]);

        $this->subscriptionServiceMock
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->with($this->user, ['plan' => 'invalid'])
            ->andThrow(new \InvalidArgumentException('Invalid plan specified'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/checkout', [
                'plan' => 'invalid'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid plan specified'
            ]);
    }

    #[Test]
    public function it_handles_checkout_server_error()
    {
        $this->user->assignRole('free'); // Give user basic role

        $this->subscriptionServiceMock
            ->shouldReceive('getSubscriptionStatus')
            ->once()
            ->with($this->user)
            ->andReturn(['has_subscription' => false]);

        $this->subscriptionServiceMock
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->with($this->user, ['plan' => 'premium'])
            ->andThrow(new \Exception('Stripe API error'));

        Log::shouldReceive('error')->once();

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/checkout', [
                'plan' => 'premium'
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to create checkout session'
            ]);
    }

    #[Test]
    public function it_can_create_billing_portal_session()
    {
        $this->user->assignRole('free'); // Give basic access
        
        $this->subscriptionServiceMock
            ->shouldReceive('createBillingPortalSession')
            ->once()
            ->with($this->user, ['return_url' => 'https://example.com/billing'])
            ->andReturn(['url' => 'https://billing.stripe.com/session123']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/billing-portal', [
                'return_url' => 'https://example.com/billing'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => ['url' => 'https://billing.stripe.com/session123'],
                'message' => 'Billing portal session created successfully'
            ]);
    }

    #[Test]
    public function it_handles_invalid_billing_portal_request()
    {
        // Test validation error for invalid URL format
        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/billing-portal', [
                'return_url' => 'invalid-url'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['return_url']);
    }

    #[Test]
    public function it_handles_billing_portal_server_error()
    {
        $this->user->assignRole('free'); // Give basic access
        
        $this->subscriptionServiceMock
            ->shouldReceive('createBillingPortalSession')
            ->once()
            ->with($this->user, [])
            ->andThrow(new \Exception('Stripe API error'));

        Log::shouldReceive('error')->once();

        $response = $this->actingAs($this->user)
            ->postJson('/api/subscription/billing-portal');

        $response->assertStatus(500)
            ->assertJson([
                'status' => 'error',
                'message' => 'Failed to create billing portal session'
            ]);
    }

    #[Test]
    public function it_requires_authentication_for_all_endpoints()
    {
        $endpoints = [
            ['GET', '/api/subscription'],
            ['POST', '/api/subscription/start-trial'],
            ['POST', '/api/subscription/checkout'],
            ['POST', '/api/subscription/billing-portal']
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertStatus(401);
        }
    }
}
