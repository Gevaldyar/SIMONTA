<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Services\TechnologyDetectionService;
use Database\Seeders\TechnologyCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologyRealWorldFixtureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jalankan fingerprint fixture melalui mesin deteksi.
     */
    private function detectFixture(string $fixtureName): array
    {
        $fixturePath = base_path(
            "tests/Fixtures/technology-fingerprints/{$fixtureName}.php"
        );

        if (! file_exists($fixturePath)) {
            throw new \RuntimeException(
                "Fixture {$fixtureName} tidak ditemukan."
            );
        }

        $fixture = require $fixturePath;

        // Muat katalog teknologi.
        $this->seed(TechnologyCatalogSeeder::class);

        // Buat data pengujian.
        $opd = Opd::create([
            'name' => 'OPD Fixture ' . $fixtureName,
        ]);

        $website = $opd->websites()->create([
            'name' => $fixture['name'],
            'url' => "https://{$fixtureName}.fixture.test/",
        ]);

        $scan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => $fixture['http_status'],
            'page_title' => $fixture['page_title'],
            'final_url' => $fixture['url'],
        ]);

        // Jalankan mesin deteksi tanpa akses internet.
        $results = app(TechnologyDetectionService::class)
            ->detect($scan, $fixture['fingerprint']);

        $detectedSlugs = $results
            ->map(fn ($item) => $item->technology?->slug)
            ->filter()
            ->values()
            ->all();

        return [
            'fixture' => $fixture,
            'results' => $results,
            'detected' => $detectedSlugs,
        ];
    }

    public function test_real_diskominfo_fingerprint_detects_nextjs(): void
    {
        $audit = $this->detectFixture('diskominfo');

        $this->assertEqualsCanonicalizing(
            $audit['fixture']['expected'],
            $audit['detected']
        );

        $nextJs = $audit['results']->first(
            fn ($item) => $item->technology?->slug === 'nextjs'
        );

        $this->assertNotNull($nextJs);
        $this->assertSame(95, $nextJs->confidence);

        $this->assertSame(
            'Next.js Static Asset Reference',
            $nextJs->evidence[0]['signature_name']
        );
    }

    public function test_real_dinkes_fingerprint_detects_laravel_and_apache(): void
    {
        $audit = $this->detectFixture('dinkes');

        $this->assertEqualsCanonicalizing(
            $audit['fixture']['expected'],
            $audit['detected']
        );

        $apache = $audit['results']->first(
            fn ($item) => $item->technology?->slug === 'apache'
        );

        $laravel = $audit['results']->first(
            fn ($item) => $item->technology?->slug === 'laravel'
        );

        $this->assertNotNull($apache);
        $this->assertNotNull($laravel);

        $this->assertSame(95, $apache->confidence);
        $this->assertSame(90, $laravel->confidence);

        $this->assertSame(
            'Laravel Session Cookie',
            $laravel->evidence[0]['signature_name']
        );
    }
    public function test_real_dinsos_dlh_and_kesbangpol_fingerprints_detect_nextjs_and_nginx(): void
{
    $fixtureNames = [
        'dinsos',
        'dlh',
        'kesbangpol',
    ];

    foreach ($fixtureNames as $fixtureName) {
        $audit = $this->detectFixture($fixtureName);

        // Memastikan hasil aktual sama dengan teknologi yang diharapkan.
        $this->assertEqualsCanonicalizing(
            $audit['fixture']['expected'],
            $audit['detected'],
            "Hasil deteksi tidak sesuai untuk {$fixtureName}"
        );

        $this->assertCount(
            23,
            $audit['fixture']['fingerprint']['scripts'],
            "Jumlah script tidak sesuai untuk {$fixtureName}"
        );

        // Memastikan Next.js terdeteksi.
        $nextJs = $audit['results']->first(
            fn ($item) => $item->technology?->slug === 'nextjs'
        );

        $this->assertNotNull($nextJs);
        $this->assertSame(95, $nextJs->confidence);

        $this->assertSame(
            'Next.js Static Asset Reference',
            $nextJs->evidence[0]['signature_name']
        );

        // Memastikan Nginx terdeteksi melalui HTTP response header.
        $nginx = $audit['results']->first(
            fn ($item) => $item->technology?->slug === 'nginx'
        );

        $this->assertNotNull($nginx);
        $this->assertSame(95, $nginx->confidence);

        $this->assertSame(
            'Nginx Server Header',
            $nginx->evidence[0]['signature_name']
        );

        // Hanya dua teknologi tersebut yang diharapkan terdeteksi.
        $this->assertCount(2, $audit['results']);
    }
}
}