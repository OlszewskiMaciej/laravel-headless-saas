<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
{
    public function test_register_endpoint_exists(): void
    {
        $response = $this->postJson('/api/auth/register');
        $response->assertStatus(422); // Expecting validation errors, not 404
    }
    
    public function test_login_endpoint_exists(): void
    {
        $response = $this->postJson('/api/auth/login');
        $response->assertStatus(422); // Expecting validation errors, not 404
    }
}
