<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable the webhook signature verification middleware for testing
        $this->withoutMiddleware(VerifyWebhookSignature::class);
    }
    
    /**
     * Test that webhook endpoints exist and accept POST requests
     */
    public function test_webhook_endpoint_exists(): void
    {
        // Create a user with a stripe ID
        $user = User::factory()->create([
            'stripe_id' => 'cus_test123',
        ]);
        $user->assignRole('premium');
        
        // Create payload for webhook
        $payload = [
            'id' => 'evt_123',
            'type' => 'customer.updated',
            'data' => [
                'object' => [
                    'id' => 'cus_test123',
                ]
            ]
        ];
        
        // Simulate the webhook call
        $response = $this->withoutMiddleware()
            ->postJson('/api/stripe/webhook', $payload, [
                'X-Stripe-Test' => 'true',
                'Stripe-Signature' => 'test_signature'
            ]);
        
        // Just verify that the endpoint exists (not a 404 or 405)
        $this->assertNotEquals(404, $response->status());
        $this->assertNotEquals(405, $response->status());
    }
}
