<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run migrations for testing
        $this->artisan('migrate');
        
        // Set up any common test data or configuration
        $this->setupTestEnvironment();
    }

    /**
     * Setup common test environment configurations
     */
    protected function setupTestEnvironment(): void
    {
        // Disable email verification for testing
        config(['auth.verification.verify' => false]);
        
        // Set testing API URL
        config(['app.url' => 'http://localhost']);
    }

    /**
     * Create an authenticated user for testing
     */
    protected function createAuthenticatedUser(array $attributes = [])
    {
        $user = \App\Models\User::factory()->verified()->create($attributes);
        $token = $user->createToken('test-token')->plainTextToken;
        
        return ['user' => $user, 'token' => $token];
    }

    /**
     * Get auth headers for API requests
     */
    protected function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get standard JSON headers
     */
    protected function jsonHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
