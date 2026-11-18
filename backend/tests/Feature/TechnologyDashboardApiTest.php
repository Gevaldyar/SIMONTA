<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Technology;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologyDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    public function test_guest_cannot_access_technology_registry(): void
    {
        $this->getJson('/api/technologies')
            ->assertUnauthorized();

        $this->getJson('/api/dashboard/summary')
            ->assertUnauthorized();
    }

    public function test_admin_can_view_seeded_technology_catalog(): void
    {
        $this->loginAsAdmin();

        $this->seed(\Database\Seeders\TechnologyCatalogSeeder::class);

        $this->getJson('/api/technologies?per_page=100')
            ->assertOk()
            ->assertJsonPath('total', 17);

        $this->getJson('/api/technologies?search=Laravel')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'laravel');

        // Memastikan pencarian Next.js berjalan dengan benar.
        $this->getJson('/api/technologies?search=Next.js')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'nextjs');

        $this->getJson('/api/technologies?category=cms')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_admin_can_view_technology_detail_and_signatures(): void
    {
        $this->loginAsAdmin();

        $this->seed(\Database\Seeders\TechnologyCatalogSeeder::class);

        $technology = Technology::where(
            'slug',
            'laravel'
        )->firstOrFail();

        $this->getJson("/api/technologies/{$technology->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Laravel')
            ->assertJsonPath('data.signatures.0.name', 'Laravel Session Cookie');
    }

    public function test_admin_can_view_dashboard_summary(): void
    {
        $this->loginAsAdmin();

        $this->seed(\Database\Seeders\TechnologyCatalogSeeder::class);

        $opd = Opd::create([
            'name' => 'Dinas Statistik',
        ]);

        $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.statistics.total_opds', 1)
            ->assertJsonPath('data.statistics.active_opds', 1)
            ->assertJsonPath('data.statistics.total_websites', 1)
            ->assertJsonPath('data.statistics.monitored_websites', 1)
            ->assertJsonPath('data.statistics.total_technologies', 17)
            ->assertJsonPath('data.statistics.total_signatures', 24);
    }

    public function test_technology_usage_counts_latest_completed_scan_per_website(): void
    {
        $this->loginAsAdmin();

        $this->seed(\Database\Seeders\TechnologyCatalogSeeder::class);

        $technology = Technology::where(
            'slug',
            'php'
        )->firstOrFail();

        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        // Scan completed pertama mendeteksi PHP.
        $firstScan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        $signature = $technology->signatures()->firstOrFail();

        $firstScan->scanTechnologies()->create([
            'technology_id' => $technology->id,
            'signature_id' => $signature->id,
            'confidence' => 95,
            'evidence' => [
                'source' => 'header',
            ],
        ]);

        // Scan failed berikutnya tidak boleh menghapus
        // hasil identifikasi dari scan completed sebelumnya.
        $website->scans()->create([
            'status' => 'failed',
            'error_message' => 'Connection timeout',
        ]);

        $this->getJson('/api/technologies?search=PHP')
            ->assertOk()
            ->assertJsonPath('data.0.websites_count', 1);

        // Scan completed terbaru sudah tidak mendeteksi PHP.
        $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        $this->getJson('/api/technologies?search=PHP')
            ->assertOk()
            ->assertJsonPath('data.0.websites_count', 0);
    }

    public function test_admin_can_view_technology_distribution(): void
    {
        $this->loginAsAdmin();

        $this->seed(\Database\Seeders\TechnologyCatalogSeeder::class);

        $this->getJson('/api/dashboard/technology-distribution')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'category',
                        'technologies_count',
                        'technology_website_matches',
                    ],
                ],
            ]);
    }

    public function test_admin_can_view_recent_scans(): void
    {
        $this->loginAsAdmin();

        $opd = Opd::create([
            'name' => 'Dinas Statistik',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        $this->getJson('/api/dashboard/recent-scans')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.website.name', 'Website Utama')
            ->assertJsonPath(
                'data.0.website.opd.name',
                'Dinas Statistik'
            );
    }
}
