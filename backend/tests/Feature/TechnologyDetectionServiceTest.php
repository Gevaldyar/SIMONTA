<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Technology;
use App\Models\Scan;
use App\Services\TechnologyDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologyDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_engine_detects_multiple_technologies(): void
    {
        // Membuat OPD dan website.
        $opd = Opd::create([
            'name' => 'Dinas Contoh',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        // Membuat riwayat pemeriksaan.
        $scan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
            'response_time_ms' => 250,
        ]);

        // =====================================
        // 1. Teknologi PHP
        // =====================================

        $php = Technology::create([
            'name' => 'PHP',
            'slug' => 'php',
            'category' => 'backend_language',
            'vendor' => 'PHP Group',
            'is_active' => true,
        ]);

        // Signature melalui HTTP header.
        $php->signatures()->create([
            'name' => 'PHP Header Detection',
            'type' => 'header',
            'target' => 'x-powered-by',
            'pattern' => 'PHP/',
            'match_type' => 'contains',
            'version_pattern' => '/PHP\/([0-9.]+)/i',
            'confidence' => 90,
            'is_active' => true,
        ]);

        // Signature kedua melalui cookie.
        $php->signatures()->create([
            'name' => 'PHP Session Cookie Detection',
            'type' => 'cookie',
            'pattern' => 'PHPSESSID',
            'match_type' => 'contains',
            'confidence' => 70,
            'is_active' => true,
        ]);

        // =====================================
        // 2. Teknologi WordPress
        // =====================================

        $wordpress = Technology::create([
            'name' => 'WordPress',
            'slug' => 'wordpress',
            'category' => 'cms',
            'vendor' => 'WordPress Foundation',
            'is_active' => true,
        ]);

        $wordpress->signatures()->create([
            'name' => 'WordPress Generator Meta',
            'type' => 'meta',
            'target' => 'generator',
            'pattern' => 'WordPress',
            'match_type' => 'contains',
            'version_pattern' => '/WordPress\s+([0-9.]+)/i',
            'confidence' => 95,
            'is_active' => true,
        ]);

        // =====================================
        // 3. Simulasi fingerprint website
        // =====================================

        $fingerprint = [
            'url' => 'https://example.com',

            'headers' => [
                'X-Powered-By' => 'PHP/8.3.2',
                'Server' => 'nginx',
            ],

            'html' => '<html><head></head></html>',

            'scripts' => [
                '/assets/app.js',
            ],

            'meta' => [
                [
                    'name' => 'generator',
                    'content' => 'WordPress 6.6.1',
                ],
            ],

            'cookies' => [
                'PHPSESSID' => 'private-session-value',
            ],
        ];

        // =====================================
        // 4. Jalankan detection engine
        // =====================================

        $service = app(TechnologyDetectionService::class);

        $results = $service->detect($scan, $fingerprint);

        // Dua teknologi berbeda ditemukan.
        $this->assertCount(2, $results);

        $phpResult = $results->firstWhere(
            'technology_id',
            $php->id
        );

        $wordpressResult = $results->firstWhere(
            'technology_id',
            $wordpress->id
        );

        // PHP berhasil dikenali.
        $this->assertNotNull($phpResult);
        $this->assertSame('8.3.2', $phpResult->version);
        $this->assertSame(90, $phpResult->confidence);

        // Dua signature PHP berhasil digabung.
        $this->assertCount(2, $phpResult->evidence);

        // WordPress berhasil dikenali.
        $this->assertNotNull($wordpressResult);
        $this->assertSame('6.6.1', $wordpressResult->version);
        $this->assertSame(95, $wordpressResult->confidence);

        // Informasi rahasia cookie tidak disimpan.
        $this->assertStringNotContainsString(
            'private-session-value',
            json_encode($phpResult->evidence)
        );

        // Data hasil identifikasi benar-benar tersimpan.
        $this->assertSame(
            2,
            $scan->scanTechnologies()->count()
        );
    }

    public function test_running_detection_again_replaces_previous_results(): void
    {
        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Pengujian',
            'url' => 'https://example.com',
        ]);

        $scan = $website->scans()->create([
            'status' => 'completed',
        ]);

        $php = Technology::create([
            'name' => 'PHP',
            'slug' => 'php',
            'category' => 'backend_language',
            'is_active' => true,
        ]);

        $php->signatures()->create([
            'name' => 'PHP Header',
            'type' => 'header',
            'target' => 'x-powered-by',
            'pattern' => 'PHP/',
            'match_type' => 'contains',
            'version_pattern' => '/PHP\/([0-9.]+)/i',
            'confidence' => 90,
            'is_active' => true,
        ]);

        $service = app(TechnologyDetectionService::class);

        // Pemeriksaan pertama menemukan PHP.
        $service->detect($scan, [
            'headers' => [
                'X-Powered-By' => 'PHP/8.3.2',
            ],
        ]);

        $this->assertSame(
            1,
            $scan->scanTechnologies()->count()
        );

        // Pemeriksaan kedua tidak menemukan teknologi.
        $service->detect($scan, []);

        // Hasil lama harus diganti.
        $this->assertSame(
            0,
            $scan->scanTechnologies()->count()
        );
    }
}
