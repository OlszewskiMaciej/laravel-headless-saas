<?php

namespace Tests\Unit\Subscription\Requests;

use App\Modules\Subscription\Requests\CheckoutRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_authorizes_all_requests()
    {
        $request = new CheckoutRequest();
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function it_validates_valid_data()
    {
        $request = new CheckoutRequest();
        $validator = Validator::make([
            'plan' => 'premium',
            'mode' => 'subscription',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'trial_days' => 14
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_minimal_valid_data()
    {
        $request = new CheckoutRequest();
        $validator = Validator::make([], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_rejects_invalid_mode()
    {
        $request = new CheckoutRequest();
        $validator = Validator::make([
            'mode' => 'invalid_mode'
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mode', $validator->errors()->toArray());
    }

    #[Test]
    public function it_rejects_invalid_urls()
    {
        $request = new CheckoutRequest();
        
        $validator = Validator::make([
            'success_url' => 'not-a-url',
            'cancel_url' => 'also-not-a-url'
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('success_url', $validator->errors()->toArray());
        $this->assertArrayHasKey('cancel_url', $validator->errors()->toArray());
    }

    #[Test]
    public function it_rejects_negative_trial_days()
    {
        $request = new CheckoutRequest();
        $validator = Validator::make([
            'trial_days' => -5
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('trial_days', $validator->errors()->toArray());
    }

    #[Test]
    public function it_accepts_zero_trial_days()
    {
        $request = new CheckoutRequest();
        $validator = Validator::make([
            'trial_days' => 0
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_accepts_valid_modes()
    {
        $request = new CheckoutRequest();
        $validModes = ['subscription', 'payment', 'setup'];

        foreach ($validModes as $mode) {
            $validator = Validator::make([
                'mode' => $mode
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Mode '{$mode}' should be valid");
        }
    }

    #[Test]
    public function it_validates_plan_as_string()
    {
        $request = new CheckoutRequest();
        
        $validator = Validator::make([
            'plan' => 123
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('plan', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_trial_days_as_integer()
    {
        $request = new CheckoutRequest();
        
        $validator = Validator::make([
            'trial_days' => 'not-an-integer'
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('trial_days', $validator->errors()->toArray());
    }
}
