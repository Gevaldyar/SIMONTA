<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\Technology;
use App\Models\Website;
use App\Services\TechnologyChangeDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologyChangeDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite(): Website
    {
        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        return $opd->websites()->create([
            'name' => 'Website Pengujian',
            'url' => 'https://example.com',
        ]);
    }

    private function createTechnology(
        string $name,
        string $slug
    ): Technology {
        return Technology::create([
            'name' => $name,
            'slug' => $slug,
            'category' => 'backend_framework',
            'is_active' => true,
        ]);
    }

    /**
     * Membuat scan completed beserta hasil deteksi.
     *
     * $startedAt dan $completedAt menggunakan timestamp.
     */
    private function createCompletedScan(
        Website $website,
        string $startedAt,
        string $completedAt,
        array $detectedTechnologies = [],
        int $httpStatus = 200
    ): Scan {
        $scan = $website->scans()->create([
            'status' => 'completed',
            'scheduled_for' => $startedAt,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'http_status_code' => $httpStatus,
            'response_time_ms' => 250,
            'final_url' => $website->url,
            'page_title' => 'Website Pengujian',
        ]);

        foreach ($detectedTechnologies as $item) {
            $scan->scanTechnologies()->create([
                'technology_id' => $item['technology']->id,
                'version' => $item['version'] ?? null,
                'confidence' => $item['confidence'] ?? 95,
                'evidence' => $item['evidence'] ?? [],
            ]);
        }

        return $scan;
    }

    public function test_first_successful_scan_becomes_baseline(): void
    {
        $website = $this->createWebsite();

        $php = $this->createTechnology('PHP', 'php');

        $scan = $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(10)->toDateTimeString(),
            completedAt: now()->subMinutes(9)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
            ]
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($scan);

        $this->assertCount(0, $changes);

        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_it_detects_added_removed_and_version_changed_technologies(): void
    {
        $website = $this->createWebsite();

        $php = $this->createTechnology('PHP', 'php');
        $laravel = $this->createTechnology('Laravel', 'laravel');
        $wordpress = $this->createTechnology('WordPress', 'wordpress');
        $nginx = $this->createTechnology('Nginx', 'nginx');

        // Scan sebelumnya.
        $previousScan = $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(10)->toDateTimeString(),
            completedAt: now()->subMinutes(9)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.2.0',
                ],
                [
                    'technology' => $laravel,
                ],
                [
                    'technology' => $wordpress,
                ],
            ]
        );

        // Scan terbaru.
        $currentScan = $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(5)->toDateTimeString(),
            completedAt: now()->subMinutes(4)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
                [
                    'technology' => $nginx,
                ],
            ]
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(4, $changes);

        $this->assertDatabaseCount('technology_changes', 4);

        $changeTypes = $changes->pluck(
            'change_type',
            'technology_slug'
        );

        // PHP mengalami perubahan versi.
        $this->assertSame(
            'version_changed',
            $changeTypes['php']
        );

        // Laravel dan WordPress tidak lagi ditemukan.
        $this->assertSame(
            'removed',
            $changeTypes['laravel']
        );

        $this->assertSame(
            'removed',
            $changeTypes['wordpress']
        );

        // Nginx baru ditemukan.
        $this->assertSame(
            'added',
            $changeTypes['nginx']
        );

        // Memastikan hubungan scan pembanding benar.
        foreach ($changes as $change) {
            $this->assertSame(
                $previousScan->id,
                $change->previous_scan_id
            );

            $this->assertSame(
                $currentScan->id,
                $change->scan_id
            );
        }

        // Memastikan snapshot versi PHP tersimpan.
        $phpChange = $changes->firstWhere(
            'technology_slug',
            'php'
        );

        $this->assertSame(
            '8.2.0',
            $phpChange->previous_version
        );

        $this->assertSame(
            '8.3.2',
            $phpChange->current_version
        );
    }

    public function test_it_does_not_create_change_when_technology_is_unchanged(): void
    {
        $website = $this->createWebsite();

        $php = $this->createTechnology('PHP', 'php');

        $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(10)->toDateTimeString(),
            completedAt: now()->subMinutes(9)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
            ]
        );

        $currentScan = $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(5)->toDateTimeString(),
            completedAt: now()->subMinutes(4)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
            ]
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(0, $changes);

        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_it_does_not_compare_when_current_scan_returns_http_error(): void
    {
        $website = $this->createWebsite();

        $php = $this->createTechnology('PHP', 'php');

        $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(10)->toDateTimeString(),
            completedAt: now()->subMinutes(9)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
            ]
        );

        $currentScan = $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(5)->toDateTimeString(),
            completedAt: now()->subMinutes(4)->toDateTimeString(),
            detectedTechnologies: [],
            httpStatus: 404
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(0, $changes);

        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_it_does_not_use_previous_http_error_as_baseline(): void
    {
        $website = $this->createWebsite();

        $php = $this->createTechnology('PHP', 'php');

        // Scan sebelumnya gagal secara HTTP.
        $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(10)->toDateTimeString(),
            completedAt: now()->subMinutes(9)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
            ],
            httpStatus: 500
        );

        // Scan terbaru berhasil.
        $currentScan = $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(5)->toDateTimeString(),
            completedAt: now()->subMinutes(4)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
            ]
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        // Tidak ada baseline sukses sebelumnya.
        $this->assertCount(0, $changes);

        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_recording_same_scan_twice_does_not_duplicate_changes(): void
    {
        $website = $this->createWebsite();

        $php = $this->createTechnology('PHP', 'php');
        $nginx = $this->createTechnology('Nginx', 'nginx');

        $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(10)->toDateTimeString(),
            completedAt: now()->subMinutes(9)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
            ]
        );

        $currentScan = $this->createCompletedScan(
            website: $website,
            startedAt: now()->subMinutes(5)->toDateTimeString(),
            completedAt: now()->subMinutes(4)->toDateTimeString(),
            detectedTechnologies: [
                [
                    'technology' => $php,
                    'version' => '8.3.2',
                ],
                [
                    'technology' => $nginx,
                ],
            ]
        );

        $service = app(TechnologyChangeDetectionService::class);

        $firstResult = $service->record($currentScan);
        $secondResult = $service->record($currentScan);

        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $secondResult);

        $this->assertDatabaseCount('technology_changes', 1);
    }
}