<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): User
    {
        $user = User::factory()->create([
            'name' => 'Administrator SIMONTA',
            'email' => 'admin@example.com',
            'password' => 'OldPassword123!',
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_profile_settings(): void
    {
        $this->getJson('/api/settings/profile')
            ->assertUnauthorized();

        $this->patchJson('/api/settings/profile', [
            'name' => 'Administrator Baru',
            'email' => 'new@example.com',
        ])->assertUnauthorized();

        $this->putJson('/api/settings/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnauthorized();
    }

    public function test_admin_can_view_own_profile(): void
    {
        $this->loginAsAdmin();

        $this->getJson('/api/settings/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Administrator SIMONTA')
            ->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_admin_can_update_own_profile(): void
    {
        $user = $this->loginAsAdmin();

        $this->patchJson('/api/settings/profile', [
            'name' => 'Administrator Baru',
            'email' => 'administrator@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Administrator Baru')
            ->assertJsonPath(
                'data.email',
                'administrator@example.com'
            );

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Administrator Baru',
            'email' => 'administrator@example.com',
        ]);
    }

    public function test_admin_cannot_use_email_owned_by_another_user(): void
    {
        $this->loginAsAdmin();

        User::factory()->create([
            'email' => 'other@example.com',
        ]);

        $this->patchJson('/api/settings/profile', [
            'name' => 'Administrator Baru',
            'email' => 'other@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_must_provide_valid_profile_data(): void
    {
        $this->loginAsAdmin();

        $this->patchJson('/api/settings/profile', [
            'name' => '',
            'email' => 'invalid-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
            ]);
    }

    public function test_admin_can_update_password_with_correct_current_password(): void
    {
        $user = $this->loginAsAdmin();

        $this->putJson('/api/settings/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Password berhasil diperbarui.'
            );

        $this->assertTrue(
            Hash::check(
                'NewPassword456!',
                $user->fresh()->password
            )
        );
    }

    public function test_admin_cannot_update_password_with_wrong_current_password(): void
    {
        $user = $this->loginAsAdmin();

        $originalPassword = $user->password;

        $this->putJson('/api/settings/password', [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertSame(
            $originalPassword,
            $user->fresh()->password
        );
    }

    public function test_new_password_requires_confirmation_and_minimum_length(): void
    {
        $this->loginAsAdmin();

        $this->putJson('/api/settings/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password',
            ]);
    }
}
