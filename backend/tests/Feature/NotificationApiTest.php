<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SimontaEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): User
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        return $user;
    }

    private function createNotification(
        User $user,
        array $payload
    ): string {
        $user->notify(new SimontaEventNotification($payload));

        return (string) $user->notifications()
            ->latest()
            ->firstOrFail()
            ->id;
    }

    private function samplePayload(
        string $type = 'technology_added'
    ): array {
        return [
            'type' => $type,
            'title' => 'Teknologi baru terdeteksi',
            'message' => 'Cloudflare terdeteksi pada website.',
            'severity' => 'info',
            'event_key' => uniqid('test-', true),
            'scan_id' => 1,
            'url' => '/technology-changes',
        ];
    }

    public function test_guest_cannot_access_notification_api(): void
    {
        $this->getJson('/api/notifications')
            ->assertUnauthorized();

        $this->getJson('/api/notifications/unread-count')
            ->assertUnauthorized();

        $this->patchJson('/api/notifications/read-all')
            ->assertUnauthorized();
    }

    public function test_user_can_view_only_own_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createNotification(
            $user,
            $this->samplePayload()
        );

        $this->createNotification(
            $user,
            $this->samplePayload('scan_failed')
        );

        $this->createNotification(
            $otherUser,
            $this->samplePayload()
        );

        $this->actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('unread_count', 2);
    }

    public function test_user_can_filter_unread_notifications_by_type(): void
    {
        $user = User::factory()->create();

        $this->createNotification(
            $user,
            $this->samplePayload('technology_added')
        );

        $this->createNotification(
            $user,
            $this->samplePayload('scan_failed')
        );

        $this->actingAs($user);

        $this->getJson(
            '/api/notifications?unread=1&type=technology_added'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'technology_added');
    }

    public function test_user_can_view_unread_notification_count(): void
    {
        $user = User::factory()->create();

        $notificationId = $this->createNotification(
            $user,
            $this->samplePayload()
        );

        $this->actingAs($user);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->patchJson(
            "/api/notifications/{$notificationId}/read"
        )->assertOk();

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_can_mark_one_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notificationId = $this->createNotification(
            $user,
            $this->samplePayload()
        );

        $this->actingAs($user);

        $this->patchJson(
            "/api/notifications/{$notificationId}/read"
        )
            ->assertOk()
            ->assertJsonPath('data.id', $notificationId)
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull(
            $user->notifications()
                ->findOrFail($notificationId)
                ->read_at
        );
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $notificationId = $this->createNotification(
            $otherUser,
            $this->samplePayload()
        );

        $this->actingAs($user);

        $this->patchJson(
            "/api/notifications/{$notificationId}/read"
        )->assertNotFound();

        $this->assertNull(
            $otherUser->notifications()
                ->findOrFail($notificationId)
                ->read_at
        );
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        $this->createNotification(
            $user,
            $this->samplePayload()
        );

        $this->createNotification(
            $user,
            $this->samplePayload('scan_failed')
        );

        $this->actingAs($user);

        $this->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('updated_count', 2)
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(
            0,
            $user->unreadNotifications()->count()
        );
    }

    public function test_notification_pagination_limit_is_validated(): void
    {
        $user = $this->authenticate();

        $this->getJson('/api/notifications?per_page=101')
            ->assertUnprocessable();
    }
}
