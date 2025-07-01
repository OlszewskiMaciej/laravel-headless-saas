<?php

namespace Tests\Unit\Subscription\Requests;

use App\Modules\Subscription\Requests\BillingPortalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingPortalRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_authorizes_all_requests()
    {
        $request = new BillingPortalRequest();
        $this->assertTrue($request->authorize());
    }

    #[Test]
    public function it_validates_valid_data()
    {
        $request = new BillingPortalRequest();
        $validator = Validator::make([
            'return_url' => 'https://example.com/billing'
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_empty_data()
    {
        $request = new BillingPortalRequest();
        $validator = Validator::make([], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_rejects_invalid_return_url()
    {
        $request = new BillingPortalRequest();
        $validator = Validator::make([
            'return_url' => 'not-a-valid-url'
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('return_url', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_return_url_as_string()
    {
        $request = new BillingPortalRequest();
        $validator = Validator::make([
            'return_url' => 123
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('return_url', $validator->errors()->toArray());
    }

    #[Test]
    public function it_accepts_various_valid_urls()
    {
        $request = new BillingPortalRequest();
        $validUrls = [
            'https://example.com',
            'http://localhost:3000',
            'https://subdomain.example.com/path',
            'https://example.com/path?query=value'
        ];

        foreach ($validUrls as $url) {
            $validator = Validator::make([
                'return_url' => $url
            ], $request->rules());

            $this->assertFalse($validator->fails(), "URL '{$url}' should be valid");
        }
    }
}
