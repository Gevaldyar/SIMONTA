<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\Technology;
use App\Models\User;
use App\Models\Website;
use App\Services\HttpInspector;
use App\Services\PublicUrlValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use App\Jobs\RunWebsiteScanJob;
use Illuminate\Support\Facades\Queue;

class ScanApiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function createWebsite(): Website
    {
        $opd = Opd::create([
            'name' => 'Dinas Contoh',
        ]);

        return $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);
    }

    /**
     * Membuat HTTP Inspector simulasi.
     * Tidak menggunakan internet sungguhan.
     */
    private function fakeInspector(array $responses): HttpInspector
    {
        $validator = new class extends PublicUrlValidator {
            protected function resolveHost(string $host): array
            {
                return ['93.184.216.34'];
            }
        };

        return new class($validator, $responses) extends HttpInspector {
            private array $responses;

            public function __construct(
                PublicUrlValidator $validator,
                array $responses
            ) {
                parent::__construct($validator);

                $this->responses = $responses;
            }

            protected function performRequest(
                string $url,
                array $validated,
                int $remainingMs
            ): array {
                $response = array_shift($this->responses);

                if (!is_array($response)) {
                    throw new RuntimeException(
                        'Respons simulasi tidak tersedia.'
                    );
                }

                return $response;
            }
        };
    }

    public function test_guest_cannot_access_scan_api(): void
    {
        $website = $this->createWebsite();

        $this->getJson('/api/scans')
            ->assertUnauthorized();

        $this->postJson(
            "/api/websites/{$website->id}/scans"
        )->assertUnauthorized();
    }

    
public function test_admin_can_manually_scan_a_website(): void
{
    $this->loginAsAdmin();

    Queue::fake();

    $website = $this->createWebsite();

    $response = $this->postJson(
        "/api/websites/{$website->id}/scans"
    );

    $response->assertAccepted()
        ->assertJsonPath('data.status', 'queued');

    $scanId = $response->json('data.id');

    // Memastikan Job masuk antrean.
    Queue::assertPushed(
        RunWebsiteScanJob::class,
        fn ($job) => $job->scanId === $scanId
    );

    // Memastikan riwayat scan tersimpan.
    $this->assertDatabaseHas('scans', [
        'id' => $scanId,
        'website_id' => $website->id,
        'status' => 'queued',
    ]);

    // Memastikan jadwal otomatis berikutnya sudah diatur.
    $this->assertNotNull(
        $website->fresh()->next_scan_at
    );
}


public function test_duplicate_manual_scan_is_rejected_while_another_scan_is_pending(): void
{
    $this->loginAsAdmin();

    Queue::fake();

    $website = $this->createWebsite();

    // Buat scan yang masih dalam antrean.
    $website->scans()->create([
        'status' => 'queued',
        'scheduled_for' => now(),
    ]);

    $this->postJson(
        "/api/websites/{$website->id}/scans"
    )->assertStatus(409);

    // Tidak boleh ada job tambahan.
    Queue::assertNothingPushed();

    // Tetap hanya ada satu riwayat scan.
    $this->assertDatabaseCount('scans', 1);
}

    public function test_admin_can_view_global_scan_history(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite();

        $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
            'response_time_ms' => 250,
        ]);

        $website->scans()->create([
            'status' => 'failed',
            'error_message' => 'Connection timeout',
        ]);

        // Seluruh riwayat.
        $this->getJson('/api/scans')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Filter berdasarkan status.
        $this->getJson('/api/scans?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');

        // Filter berdasarkan website.
        $this->getJson(
            "/api/scans?website_id={$website->id}"
        )
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_website_scan_history(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite();

        $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        $website->scans()->create([
            'status' => 'failed',
        ]);

        $this->getJson(
            "/api/websites/{$website->id}/scans"
        )
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_view_scan_detail_and_detected_technology(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite();

        $technology = Technology::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'backend_framework',
            'is_active' => true,
        ]);

        $signature = $technology->signatures()->create([
            'name' => 'Laravel Cookie',
            'type' => 'cookie',
            'pattern' => 'laravel_session',
            'match_type' => 'contains',
            'confidence' => 95,
            'is_active' => true,
        ]);

        $scan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
            'response_time_ms' => 300,
        ]);

        $scan->scanTechnologies()->create([
            'technology_id' => $technology->id,
            'signature_id' => $signature->id,
            'version' => '11.x',
            'confidence' => 95,
            'evidence' => [
                'source' => 'cookie',
            ],
        ]);

        $this->getJson(
            "/api/scans/{$scan->id}"
        )
            ->assertOk()
            ->assertJsonPath('data.id', $scan->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath(
                'data.scan_technologies.0.technology.name',
                'Laravel'
            )
            ->assertJsonPath(
                'data.scan_technologies.0.version',
                '11.x'
            );
    }

    public function test_inactive_website_cannot_be_scanned(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite();

        $website->update([
            'is_active' => false,
        ]);

        $this->postJson(
            "/api/websites/{$website->id}/scans"
        )->assertStatus(409);

        $this->assertDatabaseCount('scans', 0);
    }

    public function test_invalid_scan_status_filter_is_rejected(): void
    {
        $this->loginAsAdmin();

        $this->getJson('/api/scans?status=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
