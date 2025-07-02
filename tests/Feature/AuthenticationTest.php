<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @test */
    public function user_can_register_with_valid_data()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/signup', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'email_verified_at']
            ])
            ->assertJson([
                'message' => 'User created successfully. Please check your email to verify your account.',
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'email_verified_at' => null
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Verify password is hashed
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
    }

    /** @test */
    public function user_cannot_register_with_invalid_email()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/signup', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_cannot_register_with_duplicate_email()
    {
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/signup', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_cannot_register_with_weak_password()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ];

        $response = $this->postJson('/api/auth/signup', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function user_cannot_register_without_password_confirmation()
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/signup', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->verified()->withPassword('SecurePassword123!')->create([
            'email' => 'john@example.com'
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'email_verified_at'],
                'token',
                'expires_at'
            ])
            ->assertJson([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        User::factory()->verified()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('correct-password')
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJson([
                'errors' => [
                    'email' => ['The provided credentials are incorrect.']
                ]
            ]);
    }

    /** @test */
    public function unverified_user_cannot_login()
    {
        User::factory()->unverified()->withPassword('SecurePassword123!')->create([
            'email' => 'john@example.com'
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your email address is not verified.',
                'errors' => [
                    'email' => ['Your email address is not verified.']
                ]
            ]);
    }

    /** @test */
    public function user_can_logout_with_valid_token()
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/auth/logout', [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully'
            ]);

        // Verify token is invalidated
        $response = $this->getJson('/api/user', $this->authHeaders($auth['token']));
        $response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_logout_without_token()
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_access_protected_routes_without_token()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_access_protected_routes_with_valid_token()
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/user', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'email_verified_at', 'allergies']
            ]);
    }

    /** @test */
    public function auth_endpoints_are_rate_limited()
    {
        // Attempt to hit the rate limit (5 per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password'
            ]);
        }

        $response->assertStatus(429); // Too Many Requests
    }

    /** @test */
    public function email_verification_link_works()
    {
        $user = User::factory()->unverified()->create();
        
        // Create a signed URL similar to how Laravel creates them
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Extract the path and query string
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $query = $parsedUrl['query'] ?? '';

        $response = $this->get($path . '?' . $query);

        $response->assertStatus(200);
        
        // Verify user is now verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    /** @test */
    public function invalid_email_verification_link_fails()
    {
        $user = User::factory()->unverified()->create();
        
        // Create URL with wrong hash
        $url = route('verification.verify', [
            'id' => $user->id,
            'hash' => 'wrong-hash'
        ]);

        $response = $this->get($url);

        $response->assertStatus(400);
        
        // Verify user is still unverified
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }
} 