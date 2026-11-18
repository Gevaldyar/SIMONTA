<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Administrator can log in with valid credentials.
     */
    public function test_admin_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin-test@example.com',
            'password' => 'SimontaTestPass123!',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'admin-test@example.com',
            'password' => 'SimontaTestPass123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);

        $this->assertAuthenticatedAs($user, 'web');
    }

    /**
     * Login must fail when the password is incorrect.
     */
    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'admin-test@example.com',
        ]);

        $response = $this->postJson('/login', [
            'email' => 'admin-test@example.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Email atau password tidak sesuai.',
            ]);

        $this->assertGuest('web');
    }

    /**
     * Login requires valid input.
     */
    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);
    }

    /**
     * Guest cannot access the authenticated user endpoint.
     */
    public function test_guest_cannot_access_current_user_endpoint(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertUnauthorized();
    }

    /**
     * Authenticated administrator can access their profile.
     */
    public function test_authenticated_admin_can_access_current_user_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', $user->name)
            ->assertJsonPath('email', $user->email);
    }

    /**
     * Administrator can log out.
     */
    public function test_admin_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->postJson('/logout');

        $response->assertOk()
            ->assertJson([
                'message' => 'Logout berhasil.',
            ]);

        $this->assertGuest('web');
    }

    /**
     * Login attempts are rate limited.
     */
    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $email = 'rate-limit@example.com';

        User::factory()->create([
            'email' => $email,
        ]);

        // Five incorrect attempts are allowed.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', [
                'email' => $email,
                'password' => 'WrongPassword123!',
            ])->assertUnprocessable();
        }

        // The sixth attempt must be blocked.
        $response = $this->postJson('/login', [
            'email' => $email,
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure([
                'message',
                'retry_after',
            ]);
    }
}
