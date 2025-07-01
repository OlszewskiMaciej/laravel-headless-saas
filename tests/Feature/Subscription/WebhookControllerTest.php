<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use App\Modules\Subscription\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $webhookServiceMock;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->webhookServiceMock = $this->mock(WebhookService::class);
        $this->user = User::factory()->create([
            'stripe_id' => 'cus_test123'
        ]);
    }

    #[Test]
    public function it_handles_invoice_payment_succeeded_webhook()
    {
        $payload = [
            'id' => 'evt_test_webhook',
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

        $this->webhookServiceMock
            ->shouldReceive('handlePaymentSucceeded')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/stripe/webhook', $payload, [
            'X-Stripe-Test' => 'true'
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_handles_invoice_payment_failed_webhook()
    {
        $payload = [
            'id' => 'evt_test_webhook',
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

        $this->webhookServiceMock
            ->shouldReceive('handlePaymentFailed')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/stripe/webhook', $payload, [
            'X-Stripe-Test' => 'true'
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_handles_payment_succeeded_webhook_service_error()
    {
        $payload = [
            'id' => 'evt_test_webhook',
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

        $this->webhookServiceMock
            ->shouldReceive('handlePaymentSucceeded')
            ->once()
            ->andThrow(new \Exception('Service error'));

        Log::shouldReceive('error')->once();

        $response = $this->postJson('/api/stripe/webhook', $payload, [
            'X-Stripe-Test' => 'true'
        ]);

        $response->assertStatus(500);
    }

    #[Test]
    public function it_handles_payment_failed_webhook_service_error()
    {
        $payload = [
            'id' => 'evt_test_webhook',
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_test123',
                    'customer' => 'cus_test123',
                    'subscription' => 'sub_test123'
                ]
            ]
        ];

        $this->webhookServiceMock
            ->shouldReceive('handlePaymentFailed')
            ->once()
            ->andThrow(new \Exception('Service error'));

        Log::shouldReceive('error')->once();

        $response = $this->postJson('/api/stripe/webhook', $payload, [
            'X-Stripe-Test' => 'true'
        ]);

        $response->assertStatus(500);
    }

    #[Test]
    public function it_handles_unknown_webhook_events()
    {
        $payload = [
            'id' => 'evt_test_webhook',
            'object' => 'event',
            'type' => 'customer.updated',
            'data' => [
                'object' => [
                    'id' => 'cus_test123'
                ]
            ]
        ];

        $response = $this->postJson('/api/stripe/webhook', $payload, [
            'X-Stripe-Test' => 'true'
        ]);

        $response->assertStatus(200)
            ->assertSee('Webhook Received but not handled');
    }

    #[Test]
    public function it_processes_webhooks_without_verification_in_testing()
    {
        $payload = [
            'id' => 'evt_test_webhook',
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

        $this->webhookServiceMock
            ->shouldReceive('handlePaymentSucceeded')
            ->once()
            ->andReturn(true);

        // Should work without X-Stripe-Test header since we're in testing environment
        $response = $this->postJson('/api/stripe/webhook', $payload);

        $response->assertStatus(200);
    }
}
