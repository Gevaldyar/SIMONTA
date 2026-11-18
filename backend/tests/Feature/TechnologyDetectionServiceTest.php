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

public function test_engine_detects_nextjs_from_static_asset_reference(): void
{
    $this->seed(\Database\Seeders\TechnologyCatalogSeeder::class);

    $nextJs = Technology::where('slug', 'nextjs')->firstOrFail();

    $opd = Opd::create([
        'name' => 'Dinas Pengujian Next.js',
    ]);

    $website = $opd->websites()->create([
        'name' => 'Website Next.js',
        'url' => 'https://example.com',
    ]);

    $scan = $website->scans()->create([
        'status' => 'completed',
        'http_status_code' => 200,
    ]);

    // Simulasi HTML yang ditemukan pada website Diskominfo.
    $fingerprint = [
        'html' => '<html>
            <head>
                <script src="/_next/static/chunks/app.js" async></script>
                <script src="/_next/static/chunks/webpack.js"></script>
            </head>
            <body>
                <h1>Website Next.js</h1>
            </body>
        </html>',
    ];

    $results = app(TechnologyDetectionService::class)
        ->detect($scan, $fingerprint);

    $nextJsResult = $results->firstWhere(
        'technology_id',
        $nextJs->id
    );

    $this->assertNotNull($nextJsResult);
    $this->assertSame(95, $nextJsResult->confidence);
    $this->assertSame(
        'Next.js Static Asset Reference',
        $nextJsResult->evidence[0]['signature_name']
    );
    $this->assertNull($nextJsResult->version);
    $this->assertSame(1, $scan->scanTechnologies()->count());
}

public function test_engine_does_not_detect_nextjs_from_plain_text_marker(): void
{
    $this->seed(\Database\Seeders\TechnologyCatalogSeeder::class);

    $nextJs = Technology::where('slug', 'nextjs')->firstOrFail();

    $opd = Opd::create([
        'name' => 'Dinas Pengujian Negatif',
    ]);

    $website = $opd->websites()->create([
        'name' => 'Website HTML Biasa',
        'url' => 'https://example.com',
    ]);

    $scan = $website->scans()->create([
        'status' => 'completed',
        'http_status_code' => 200,
    ]);

    // Marker hanya ada sebagai teks biasa, bukan atribut src atau href.
    $fingerprint = [
        'html' => '<html>
            <body>
                Dokumentasi ini menyebut
                /_next/static/chunks/app.js
                sebagai contoh path.
            </body>
        </html>',
    ];

    $results = app(TechnologyDetectionService::class)
        ->detect($scan, $fingerprint);

    $nextJsResult = $results->firstWhere(
        'technology_id',
        $nextJs->id
    );

    $this->assertNull($nextJsResult);
    $this->assertSame(0, $scan->scanTechnologies()->count());
}

public function test_engine_detects_nuxt_sveltekit_and_astro(): void
{
    $this->seed(
        \Database\Seeders\TechnologyCatalogSeeder::class
    );

    $opd = Opd::create([
        'name' => 'Dinas Pengujian Framework',
    ]);

    $website = $opd->websites()->create([
        'name' => 'Website Framework',
        'url' => 'https://example.com',
    ]);

    $scan = $website->scans()->create([
        'status' => 'completed',
        'http_status_code' => 200,
    ]);

    $fingerprint = [
        'html' => '
            <html>
                <head>
                    <script src="/_nuxt/entry.js"></script>

                    <script src="/_app/immutable/entry/start.js"></script>

                    <link rel="stylesheet" href="/_astro/index.css">
                </head>
                <body>
                    <h1>Website Framework</h1>
                </body>
            </html>
        ',
    ];

    $results = app(TechnologyDetectionService::class)
        ->detect($scan, $fingerprint);

    $detectedSlugs = $results
        ->map(fn ($item) => $item->technology?->slug)
        ->filter()
        ->all();

    $this->assertEqualsCanonicalizing(
        ['nuxt', 'sveltekit', 'astro'],
        $detectedSlugs
    );

    $this->assertCount(3, $results);
    $this->assertSame(3, $scan->scanTechnologies()->count());
}

public function test_engine_does_not_detect_frameworks_from_plain_text_paths(): void
{
    $this->seed(
        \Database\Seeders\TechnologyCatalogSeeder::class
    );

    $opd = Opd::create([
        'name' => 'Dinas Pengujian False Positive',
    ]);

    $website = $opd->websites()->create([
        'name' => 'Website HTML Biasa',
        'url' => 'https://example.com',
    ]);

    $scan = $website->scans()->create([
        'status' => 'completed',
        'http_status_code' => 200,
    ]);

    $fingerprint = [
        'html' => '
            <html>
                <body>
                    Dokumentasi Nuxt menggunakan /_nuxt/entry.js.
                    Dokumentasi SvelteKit menggunakan /_app/immutable/.
                    Dokumentasi Astro menggunakan /_astro/index.css.
                </body>
            </html>
        ',
    ];

    $results = app(TechnologyDetectionService::class)
        ->detect($scan, $fingerprint);

    $this->assertCount(0, $results);

    $this->assertSame(
        0,
        $scan->scanTechnologies()->count()
    );
}

}
