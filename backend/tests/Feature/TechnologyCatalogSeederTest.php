<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Technology;
use App\Models\TechnologySignature;
use App\Services\TechnologyDetectionService;
use Database\Seeders\TechnologyCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnologyCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_technology_catalog_and_signatures(): void
    {
        $this->seed(TechnologyCatalogSeeder::class);

        $this->assertSame(17, Technology::count());
        $this->assertSame(24, TechnologySignature::count());

        $this->assertDatabaseHas('technologies', [
    'name' => 'Next.js',
    'slug' => 'nextjs',
    'category' => 'frontend_framework',
]);

        $this->assertDatabaseHas('technologies', [
            'name' => 'Laravel',
            'slug' => 'laravel',
            'category' => 'backend_framework',
        ]);

        $this->assertDatabaseHas('technologies', [
            'name' => 'WordPress',
            'slug' => 'wordpress',
            'category' => 'cms',
        ]);

        $this->assertDatabaseHas('technology_signatures', [
            'name' => 'Laravel Session Cookie',
            'pattern' => 'laravel_session',
        ]);

        $this->assertDatabaseHas('technologies', [
    'slug' => 'nuxt',
    'category' => 'frontend_framework',
]);

$this->assertDatabaseHas('technologies', [
    'slug' => 'sveltekit',
    'category' => 'frontend_framework',
]);

$this->assertDatabaseHas('technologies', [
    'slug' => 'astro',
    'category' => 'frontend_framework',
]);
    }

    public function test_running_seeder_twice_does_not_create_duplicates(): void
    {
        $this->seed(TechnologyCatalogSeeder::class);
        $this->seed(TechnologyCatalogSeeder::class);

        $this->assertSame(17, Technology::count());
        $this->assertSame(24, TechnologySignature::count());
    }

    public function test_detection_engine_uses_seeded_catalog(): void
    {
        $this->seed(TechnologyCatalogSeeder::class);

        $opd = Opd::create([
            'name' => 'Dinas Pengujian Katalog',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Pengujian',
            'url' => 'https://example.com',
        ]);

        $scan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        // Simulasi fingerprint website.
        $fingerprint = [
            'url' => 'https://example.com',
            'final_url' => 'https://example.com',

            'headers' => [
                'x-powered-by' => ['PHP/8.3.2'],
                'server' => ['nginx/1.24.0'],
                'cf-cache-status' => ['HIT'],
            ],

            'cookies' => [
                'laravel_session',
            ],

            'meta' => [
                [
                    'name' => 'generator',
                    'content' => 'WordPress 6.6.1',
                ],
            ],

            'scripts' => [
                '/wp-content/themes/example/app.js',
                '/assets/jquery-3.7.1.min.js',
            ],

            'html' => '<html><head></head><body></body></html>',
        ];

        $results = app(TechnologyDetectionService::class)
            ->detect($scan, $fingerprint);

        // Memastikan enam teknologi berhasil dikenali.
        $this->assertCount(6, $results);

        $detectedSlugs = $results
            ->map(fn ($result) => $result->technology->slug)
            ->all();

        $this->assertContains('php', $detectedSlugs);
        $this->assertContains('laravel', $detectedSlugs);
        $this->assertContains('wordpress', $detectedSlugs);
        $this->assertContains('nginx', $detectedSlugs);
        $this->assertContains('cloudflare', $detectedSlugs);
        $this->assertContains('jquery', $detectedSlugs);

        // Versi berhasil diambil dari fingerprint.
        $phpResult = $results->firstWhere(
            'technology.slug',
            'php'
        );

        $wordpressResult = $results->firstWhere(
            'technology.slug',
            'wordpress'
        );

        $this->assertSame('8.3.2', $phpResult->version);
        $this->assertSame('6.6.1', $wordpressResult->version);

        // Semua hasil benar-benar disimpan.
        $this->assertSame(
            6,
            $scan->scanTechnologies()->count()
        );
    }
}
