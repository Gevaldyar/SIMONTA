<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\ScanTechnology;
use App\Models\Technology;
use App\Models\TechnologyChange;
use App\Models\Website;
use App\Services\TechnologyChangeDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologyDetectionServiceTest extends TestCase{
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

    private function createTechnology(string $name, string $slug): Technology
    {
        return Technology::create([
            'name' => $name,
            'slug' => $slug,
            'category' => 'backend_framework',
            'vendor' => 'Test Vendor',
            'is_active' => true,
        ]);
    }

    private function createScan(
        Website $website,
        string $startedAt,
        string $completedAt,
        int $httpStatus = 200,
        string $status = 'completed'
    ): Scan {
        return Scan::create([
            'website_id' => $website->id,
            'status' => $status,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'http_status_code' => $httpStatus,
            'final_url' => $website->url,
        ]);
    }

    private function addDetection(
        Scan $scan,
        Technology $technology,
        ?string $version = null,
        int $confidence = 95,
        array $evidence = []
    ): ScanTechnology {
        return ScanTechnology::create([
            'scan_id' => $scan->id,
            'technology_id' => $technology->id,
            'version' => $version,
            'confidence' => $confidence,
            'evidence' => $evidence ?: [
                'type' => 'test',
                'matched' => $technology->slug,
            ],
        ]);
    }

    public function test_first_successful_scan_becomes_baseline_without_creating_changes(): void
    {
        $website = $this->createWebsite();
        $php = $this->createTechnology('PHP', 'php');

        $firstScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );

        $this->addDetection($firstScan, $php, '8.3.2');

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($firstScan);

        $this->assertCount(0, $changes);
        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_new_technology_is_recorded_as_added(): void
    {
        $website = $this->createWebsite();
        $php = $this->createTechnology('PHP', 'php');
        $laravel = $this->createTechnology('Laravel', 'laravel');

        $previousScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );
        $this->addDetection($previousScan, $php, '8.3.2');

        $currentScan = $this->createScan(
            $website,
            '2026-11-18 10:00:00',
            '2026-11-18 10:00:05'
        );
        $this->addDetection($currentScan, $php, '8.3.2');
        $currentDetection = $this->addDetection(
            $currentScan,
            $laravel,
            '13.0.0',
            95,
            ['type' => 'cookie', 'matched' => 'laravel_session']
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(1, $changes);

        $change = $changes->first();
        $this->assertSame('added', $change->change_type);
        $this->assertSame($laravel->id, $change->technology_id);
        $this->assertSame($previousScan->id, $change->previous_scan_id);
        $this->assertNull($change->previous_version);
        $this->assertSame('13.0.0', $change->current_version);
        $this->assertSame($currentDetection->evidence, $change->current_evidence);
    }

    public function test_missing_technology_is_recorded_as_removed(): void
    {
        $website = $this->createWebsite();
        $php = $this->createTechnology('PHP', 'php');
        $laravel = $this->createTechnology('Laravel', 'laravel');

        $previousScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );
        $this->addDetection($previousScan, $php, '8.3.2');
        $previousDetection = $this->addDetection(
            $previousScan,
            $laravel,
            '13.0.0',
            95,
            ['type' => 'cookie', 'matched' => 'laravel_session']
        );

        $currentScan = $this->createScan(
            $website,
            '2026-11-18 10:00:00',
            '2026-11-18 10:00:05'
        );
        $this->addDetection($currentScan, $php, '8.3.2');

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(1, $changes);

        $change = $changes->first();
        $this->assertSame('removed', $change->change_type);
        $this->assertSame($laravel->id, $change->technology_id);
        $this->assertSame($previousScan->id, $change->previous_scan_id);
        $this->assertSame('13.0.0', $change->previous_version);
        $this->assertNull($change->current_version);
        $this->assertSame($previousDetection->evidence, $change->previous_evidence);
    }

    public function test_changed_non_empty_version_is_recorded_as_version_changed(): void
    {
        $website = $this->createWebsite();
        $apache = $this->createTechnology('Apache', 'apache');

        $previousScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );
        $this->addDetection($previousScan, $apache, '2.4.41');

        $currentScan = $this->createScan(
            $website,
            '2026-11-18 10:00:00',
            '2026-11-18 10:00:05'
        );
        $this->addDetection($currentScan, $apache, '2.4.58');

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(1, $changes);

        $change = $changes->first();
        $this->assertSame('version_changed', $change->change_type);
        $this->assertSame('2.4.41', $change->previous_version);
        $this->assertSame('2.4.58', $change->current_version);
        $this->assertSame($previousScan->id, $change->previous_scan_id);
    }

    public function test_identical_detection_results_do_not_create_changes(): void
    {
        $website = $this->createWebsite();
        $php = $this->createTechnology('PHP', 'php');

        $previousScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );
        $this->addDetection($previousScan, $php, '8.3.2');

        $currentScan = $this->createScan(
            $website,
            '2026-11-18 10:00:00',
            '2026-11-18 10:00:05'
        );
        $this->addDetection($currentScan, $php, '8.3.2');

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(0, $changes);
        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_http_404_scan_does_not_create_false_removed_changes(): void
    {
        $website = $this->createWebsite();
        $php = $this->createTechnology('PHP', 'php');

        $previousScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );
        $this->addDetection($previousScan, $php, '8.3.2');

        $currentScan = $this->createScan(
            $website,
            '2026-11-18 10:00:00',
            '2026-11-18 10:00:05',
            404
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(0, $changes);
        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_failed_scan_does_not_create_false_removed_changes(): void
    {
        $website = $this->createWebsite();
        $php = $this->createTechnology('PHP', 'php');

        $previousScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );
        $this->addDetection($previousScan, $php, '8.3.2');

        $currentScan = $this->createScan(
            $website,
            '2026-11-18 10:00:00',
            '2026-11-18 10:00:05',
            500,
            'failed'
        );

        $changes = app(TechnologyChangeDetectionService::class)
            ->record($currentScan);

        $this->assertCount(0, $changes);
        $this->assertDatabaseCount('technology_changes', 0);
    }

    public function test_recording_same_scan_twice_does_not_duplicate_changes(): void
    {
        $website = $this->createWebsite();
        $php = $this->createTechnology('PHP', 'php');
        $cloudflare = $this->createTechnology('Cloudflare', 'cloudflare');

        $previousScan = $this->createScan(
            $website,
            '2026-11-18 09:00:00',
            '2026-11-18 09:00:05'
        );
        $this->addDetection($previousScan, $php, '8.3.2');

        $currentScan = $this->createScan(
            $website,
            '2026-11-18 10:00:00',
            '2026-11-18 10:00:05'
        );
        $this->addDetection($currentScan, $php, '8.3.2');
        $this->addDetection($currentScan, $cloudflare);

        $service = app(TechnologyChangeDetectionService::class);

        $firstRun = $service->record($currentScan);
        $secondRun = $service->record($currentScan);

        $this->assertCount(1, $firstRun);
        $this->assertCount(1, $secondRun);
        $this->assertDatabaseCount('technology_changes', 1);
        $this->assertSame(
            'added',
            TechnologyChange::query()->firstOrFail()->change_type
        );
    }
}
