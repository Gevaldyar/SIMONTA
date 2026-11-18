<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpdWebsiteApiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    public function test_guest_cannot_access_opd_api(): void
    {
        $this->getJson('/api/opds')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_and_list_opds(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/api/opds', [
            'name' => 'Dinas Komunikasi dan Informatika',
            'code' => 'DISKOMINFO',
            'email' => 'kominfo@example.com',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Dinas Komunikasi dan Informatika'
            );

        $this->getJson('/api/opds')
            ->assertOk()
            ->assertJsonPath(
                'data.0.code',
                'DISKOMINFO'
            );
    }

    public function test_one_opd_can_have_multiple_websites_through_api(): void
    {
        $this->loginAsAdmin();

        $opd = Opd::create([
            'name' => 'Dinas Contoh',
        ]);

        $first = $this->postJson(
            "/api/opds/{$opd->id}/websites",
            [
                'name' => 'Website Utama',
                'url' => 'https://example.com',
            ]
        );

        $first->assertCreated()
            ->assertJsonPath('data.monitoring_interval_minutes', 60);

        $this->postJson(
            "/api/opds/{$opd->id}/websites",
            [
                'name' => 'Portal Layanan',
                'url' => 'https://service.example.com',
            ]
        )->assertCreated();

        $this->assertSame(
            2,
            $opd->websites()->count()
        );

        $this->getJson(
            "/api/opds/{$opd->id}/websites"
        )
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_website_validation_rejects_invalid_url_and_interval(): void
    {
        $this->loginAsAdmin();

        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $this->postJson(
            "/api/opds/{$opd->id}/websites",
            [
                'name' => 'Website Invalid',
                'url' => 'ftp://example.com',
                'monitoring_interval_minutes' => 30,
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url',
                'monitoring_interval_minutes',
            ]);
    }

    public function test_duplicate_website_url_is_rejected(): void
    {
        $this->loginAsAdmin();

        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $opd->websites()->create([
            'name' => 'Website Pertama',
            'url' => 'https://example.com',
        ]);

        $this->postJson(
            "/api/opds/{$opd->id}/websites",
            [
                'name' => 'Website Kedua',
                'url' => 'https://example.com',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_website_can_be_updated(): void
    {
        $this->loginAsAdmin();

        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Lama',
            'url' => 'https://example.com',
        ]);

        $this->patchJson(
            "/api/websites/{$website->id}",
            [
                'name' => 'Website Baru',
                'monitoring_enabled' => false,
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.name', 'Website Baru')
            ->assertJsonPath('data.monitoring_enabled', false);

        $this->assertDatabaseHas('websites', [
            'id' => $website->id,
            'name' => 'Website Baru',
            'monitoring_enabled' => false,
        ]);
    }

    public function test_opd_cannot_be_deleted_while_it_has_websites(): void
    {
        $this->loginAsAdmin();

        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        $this->deleteJson("/api/opds/{$opd->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('opds', [
            'id' => $opd->id,
            'deleted_at' => null,
        ]);
    }

    public function test_website_and_opd_can_be_soft_deleted_in_order(): void
    {
        $this->loginAsAdmin();

        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        $this->deleteJson(
            "/api/websites/{$website->id}"
        )->assertOk();

        $this->assertSoftDeleted('websites', [
            'id' => $website->id,
        ]);

        $this->deleteJson(
            "/api/opds/{$opd->id}"
        )->assertOk();

        $this->assertSoftDeleted('opds', [
            'id' => $opd->id,
        ]);
    }
}
