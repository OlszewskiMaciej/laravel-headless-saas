<?php

namespace Tests\Unit\Subscription;

use App\Models\User;
use App\Modules\Subscription\Services\WebhookService;
use App\Modules\User\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Event;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookService $webhookService;
    private MockInterface $userRepositoryMock;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->userRepositoryMock = $this->mock(UserRepositoryInterface::class);
        $this->webhookService = new WebhookService($this->userRepositoryMock);
        
        $this->user = User::factory()->create([
            'stripe_id' => 'cus_test123'
        ]);
        $this->user->assignRole('free');
    }

    #[Test]
    public function it_handles_successful_payment_for_existing_user()
    {
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123'
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->once()
            ->with(\Mockery::on(function ($user) {
                return $user instanceof User && $user->stripe_id === 'cus_test123';
            }), ['premium']);

        Log::shouldReceive('info')->twice(); // One for payment succeeded, one for role update
        Log::shouldReceive('error')->zeroOrMoreTimes(); // Allow error logging

        $result = $this->webhookService->handlePaymentSucceeded($event);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_successful_payment_for_admin_user()
    {
        $this->user->assignRole('admin');
        
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123'
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->once()
            ->with(\Mockery::on(function ($user) {
                return $user instanceof User && $user->stripe_id === 'cus_test123' && $user->hasRole('admin');
            }), ['admin', 'premium']);

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('error')->zeroOrMoreTimes(); // Allow error logging

        $result = $this->webhookService->handlePaymentSucceeded($event);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_skips_payment_succeeded_for_non_subscription_invoice()
    {
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123'
                    // No subscription field
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->never();

        $result = $this->webhookService->handlePaymentSucceeded($event);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_payment_succeeded_for_unknown_customer()
    {
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_unknown',
                    'subscription' => 'sub_test123'
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        Log::shouldReceive('info')->once(); // General payment succeeded log
        Log::shouldReceive('warning')->once(); // User not found warning

        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->never();

        $result = $this->webhookService->handlePaymentSucceeded($event);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_payment_succeeded_with_array_payload()
    {
        $payload = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123'
                ]
            ]
        ];

        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->once()
            ->with(\Mockery::on(function ($user) {
                return $user instanceof User && $user->stripe_id === 'cus_test123';
            }), ['premium']);

        Log::shouldReceive('info')->twice();
        Log::shouldReceive('error')->zeroOrMoreTimes(); // Allow error logging

        $result = $this->webhookService->handlePaymentSucceeded($payload);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_payment_failed_for_existing_user()
    {
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123',
                    'attempt_count' => 2
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        Log::shouldReceive('warning')->once(); // Payment failed warning

        $result = $this->webhookService->handlePaymentFailed($event);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_skips_payment_failed_for_non_subscription_invoice()
    {
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123'
                    // No subscription field
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        $result = $this->webhookService->handlePaymentFailed($event);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_payment_failed_for_unknown_customer()
    {
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_unknown',
                    'subscription' => 'sub_test123',
                    'attempt_count' => 1
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        Log::shouldReceive('warning')->twice(); // Payment failed + user not found

        $result = $this->webhookService->handlePaymentFailed($event);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_handles_payment_failed_with_array_payload()
    {
        $payload = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123',
                    'attempt_count' => 3
                ]
            ]
        ];

        Log::shouldReceive('warning')->once();

        $result = $this->webhookService->handlePaymentFailed($payload);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_exception_on_payment_succeeded_error()
    {
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123'
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->once()
            ->andThrow(new \Exception('Database error'));

        Log::shouldReceive('info')->once();
        Log::shouldReceive('error')->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->webhookService->handlePaymentSucceeded($event);
    }

    #[Test]
    public function it_throws_exception_on_payment_failed_error()
    {
        // Mock Event::constructFrom to throw an exception
        $invalidPayload = [
            'invalid' => 'data'
        ];

        Log::shouldReceive('error')->once();

        $this->expectException(\Exception::class);

        $this->webhookService->handlePaymentFailed($invalidPayload);
    }

    #[Test]
    public function it_does_not_update_role_if_user_already_has_premium()
    {
        $this->user->assignRole('premium');
        
        $eventData = [
            'id' => 'evt_test',
            'object' => 'event',
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123'
                ]
            ]
        ];

        $event = Event::constructFrom($eventData);

        $this->userRepositoryMock
            ->shouldReceive('syncRoles')
            ->never();

        Log::shouldReceive('info')->once(); // Only general payment succeeded log

        $result = $this->webhookService->handlePaymentSucceeded($event);

        $this->assertTrue($result);
    }
}
