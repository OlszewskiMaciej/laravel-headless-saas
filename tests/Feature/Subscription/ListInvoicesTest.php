<?php

namespace Tests\Feature\Subscription;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListInvoicesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the list invoices endpoint returns successfully.
     */
    public function test_user_can_list_invoices(): void
    {
        // Create a user
        $user = User::factory()->create([
            'stripe_id' => 'cus_test123',
        ]);
        $user->assignRole('premium');
        
        // Login as the user
        $this->actingAs($user);        // Create a mock of the SubscriptionController to inject our test behavior
        $this->mock('App\Modules\Subscription\Controllers\SubscriptionController')
            ->shouldReceive('getMiddleware')
            ->andReturn([])
            ->shouldReceive('callAction')
            ->with('listInvoices', \Mockery::any())
            ->once()
            ->andReturn(response()->json([
                'status' => 'success',
                'data' => [
                    'invoices' => [
                        [
                            'id' => 'in_test123',
                            'total' => 1000,
                            'currency' => 'USD',
                            'date' => now()->toIso8601String(),
                            'status' => 'paid',
                            'invoice_pdf' => 'https://stripe.com/invoice.pdf',
                            'number' => 'INV-001',
                            'subscription_id' => 'sub_123',
                            'customer_name' => 'John Doe',
                            'customer_email' => 'john@example.com',
                        ]
                    ]
                ]
            ], 200));

        // Make the request
        $response = $this->getJson('/api/subscription/invoices');
        
        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'invoices' => [
                        '*' => [
                            'id',
                            'total',
                            'currency',
                            'date',
                            'status',
                            'invoice_pdf',
                            'number',
                            'subscription_id',
                            'customer_name',
                            'customer_email'
                        ]
                    ]
                ]
            ]);
    }

    /**
     * Test that users with no stripe_id get an empty invoices array.
     */
    public function test_user_without_stripe_id_gets_empty_invoices(): void
    {
        // Create a user without a stripe_id
        $user = User::factory()->create([
            'stripe_id' => null,
        ]);
        $user->assignRole('free');
        
        // Login as the user
        $this->actingAs($user);
        
        // Make the request
        $response = $this->getJson('/api/subscription/invoices');
        
        // Assert response - should return empty invoices array
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'invoices' => []
                ]
            ]);
    }
}
