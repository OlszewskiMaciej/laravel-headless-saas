<?php

namespace Tests\Unit\Subscription;

use App\Models\User;
use App\Modules\Subscription\Repositories\Interfaces\SubscriptionRepositoryInterface;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $subscriptionService;
    private MockInterface $subscriptionRepositoryMock;
    private MockInterface $userRepositoryMock;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->subscriptionRepositoryMock = $this->mock(SubscriptionRepositoryInterface::class);
        $this->userRepositoryMock = $this->mock(UserRepositoryInterface::class);
        
        $this->subscriptionService = new SubscriptionService(
            $this->subscriptionRepositoryMock,
            $this->userRepositoryMock
        );
        
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_returns_trial_status_for_user_with_active_trial_and_no_stripe_id()
    {
        $this->user->trial_ends_at = Carbon::now()->addDays(7);
        $this->user->stripe_id = null;

        $result = $this->subscriptionService->getSubscriptionStatus($this->user);

        $this->assertEquals('trial', $result['status']);
        $this->assertTrue($result['has_subscription']);
        $this->assertTrue($result['on_trial']);
        $this->assertEquals($this->user->trial_ends_at, $result['trial_ends_at']);
        $this->assertEquals('trial', $result['plan']);
        $this->assertEquals('local', $result['source']);
    }

    #[Test]
    public function it_returns_free_status_for_user_with_expired_trial_and_no_stripe_id()
    {
        $this->user->trial_ends_at = Carbon::now()->subDays(1);
        $this->user->stripe_id = null;

        $result = $this->subscriptionService->getSubscriptionStatus($this->user);

        $this->assertEquals('free', $result['status']);
        $this->assertFalse($result['has_subscription']);
        $this->assertFalse($result['on_trial']);
        $this->assertEquals('free', $result['plan']);
        $this->assertEquals('local', $result['source']);
    }

    #[Test]
    public function it_returns_free_status_for_user_with_no_trial_and_no_stripe_id()
    {
        $this->user->trial_ends_at = null;
        $this->user->stripe_id = null;

        $result = $this->subscriptionService->getSubscriptionStatus($this->user);

        $this->assertEquals('free', $result['status']);
        $this->assertFalse($result['has_subscription']);
        $this->assertFalse($result['on_trial']);
        $this->assertEquals('free', $result['plan']);
        $this->assertEquals('local', $result['source']);
    }

    #[Test]
    public function it_returns_trial_status_for_user_with_active_trial_and_stripe_id()
    {
        $this->user->trial_ends_at = Carbon::now()->addDays(7);
        $this->user->stripe_id = 'cus_test123';

        $result = $this->subscriptionService->getSubscriptionStatus($this->user);

        $this->assertEquals('trial', $result['status']);
        $this->assertTrue($result['has_subscription']);
        $this->assertTrue($result['on_trial']);
        $this->assertEquals($this->user->trial_ends_at, $result['trial_ends_at']);
        $this->assertEquals('trial', $result['plan']);
        $this->assertEquals('local', $result['source']);
    }

    #[Test]
    public function it_starts_trial_for_eligible_user()
    {
        $this->user->trial_ends_at = null;
        $this->user->save();
        
        $trialDays = 14;
        Config::set('subscription.trial_days', $trialDays);

        // Mock repository expectations
        $this->subscriptionRepositoryMock
            ->shouldReceive('isUserOnTrial')
            ->with($this->user)
            ->once()
            ->andReturn(false);
            
        $this->subscriptionRepositoryMock
            ->shouldReceive('isUserSubscribed')
            ->with($this->user)
            ->once()
            ->andReturn(false);
            
        $this->subscriptionRepositoryMock
            ->shouldReceive('startTrial')
            ->with($this->user, $trialDays)
            ->once()
            ->andReturn(true);
            
        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->with($this->user, ['trial'])
            ->once()
            ->andReturn(true);

        $result = $this->subscriptionService->startTrial($this->user);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_exception_when_user_already_has_active_trial()
    {
        $this->user->trial_ends_at = Carbon::now()->addDays(7);
        $this->user->save();

        // Mock repository expectations
        $this->subscriptionRepositoryMock
            ->shouldReceive('isUserOnTrial')
            ->with($this->user)
            ->once()
            ->andReturn(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('You have already used your trial period');

        $this->subscriptionService->startTrial($this->user);
    }

    #[Test]
    public function it_validates_checkout_data()
    {
        $invalidData = [
            'plan' => '', // Invalid empty plan
        ];

        $this->expectException(\InvalidArgumentException::class);

        $this->subscriptionService->createCheckoutSession($this->user, $invalidData);
    }

    #[Test]
    public function it_throws_exception_for_billing_portal_without_stripe_customer()
    {
        $this->user->stripe_id = null;
        
        $data = [
            'return_url' => 'https://example.com/billing'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User does not have a Stripe customer ID');

        $this->subscriptionService->createBillingPortalSession($this->user, $data);
    }

    #[Test]
    public function it_maps_stripe_status_correctly()
    {
        $testCases = [
            'active' => 'active',
            'trialing' => 'trial', 
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'expired',
            'unpaid' => 'canceled',
            'unknown_status' => 'unknown'
        ];

        $reflectionClass = new \ReflectionClass($this->subscriptionService);
        $method = $reflectionClass->getMethod('mapStripeStatus');
        $method->setAccessible(true);

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->subscriptionService, $input);
            $this->assertEquals($expected, $result, "Failed mapping {$input} to {$expected}");
        }
    }

    #[Test]
    public function it_handles_fallback_configuration()
    {
        Config::set('subscription.fallback.enabled', true);
        Config::set('subscription.fallback.log_fallback_events', true);
        Config::set('subscription.fallback.max_local_data_age_hours', 24);

        $this->assertTrue(config('subscription.fallback.enabled'));
        $this->assertTrue(config('subscription.fallback.log_fallback_events'));
        $this->assertEquals(24, config('subscription.fallback.max_local_data_age_hours'));
    }
}
