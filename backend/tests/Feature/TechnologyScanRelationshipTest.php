<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Technology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologyScanRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_stores_detected_technologies_and_evidence(): void
    {
        // Membuat OPD.
        $opd = Opd::create([
            'name' => 'Dinas Contoh',
        ]);

        // Membuat website milik OPD.
        $website = $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        // Membuat katalog teknologi.
        $technology = Technology::create([
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'backend_framework',
            'vendor' => 'Laravel',
            'description' => 'PHP Web Application Framework',
            'is_active' => true,
        ]);

        // Membuat aturan identifikasi teknologi.
        $signature = $technology->signatures()->create([
            'name' => 'Laravel Cookie Detection',
            'type' => 'cookie',
            'target' => null,
            'pattern' => 'laravel_session',
            'match_type' => 'contains',
            'confidence' => 95,
            'is_active' => true,
        ]);

        // Membuat riwayat pemeriksaan.
        $scan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
            'response_time_ms' => 320,
            'metadata' => [
                'server' => 'nginx',
            ],
        ]);

        // Menyimpan teknologi yang ditemukan.
        $result = $scan->scanTechnologies()->create([
            'technology_id' => $technology->id,
            'signature_id' => $signature->id,
            'version' => '11.x',
            'confidence' => 95,
            'evidence' => [
                'source' => 'cookie',
                'matched' => 'laravel_session',
            ],
        ]);

        // Memastikan relasi website dan scan.
        $this->assertSame(
            $website->id,
            $scan->website->id
        );

        $this->assertCount(1, $website->scans);

        // Memastikan teknologi ditemukan dalam pemeriksaan.
        $this->assertSame(
            $technology->id,
            $result->technology->id
        );

        // Memastikan signature terhubung dengan hasil identifikasi.
        $this->assertSame(
            $signature->id,
            $result->signature->id
        );

        // Memastikan relasi katalog teknologi.
        $this->assertCount(1, $technology->scanTechnologies);

        $this->assertCount(1, $signature->scanTechnologies);

        // Memastikan metadata tersimpan sebagai array.
        $this->assertSame(
            'nginx',
            $scan->fresh()->metadata['server']
        );

        // Memastikan evidence tersimpan sebagai array.
        $this->assertSame(
            'cookie',
            $result->fresh()->evidence['source']
        );

        // Memastikan versi teknologi tersimpan.
        $this->assertSame(
            '11.x',
            $result->fresh()->version
        );
    }
}
