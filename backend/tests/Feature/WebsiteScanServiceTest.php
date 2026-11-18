<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Technology;
use App\Services\HttpInspector;
use App\Services\PublicUrlValidator;
use App\Services\WebsiteScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WebsiteScanServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membuat HTTP Inspector simulasi.
     * Tidak mengakses website sungguhan.
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

    public function test_website_scan_completes_and_stores_detected_technology(): void
    {
        // Buat OPD dan website.
        $opd = Opd::create([
            'name' => 'Dinas Contoh',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Utama',
            'url' => 'https://example.com',
        ]);

        // Buat katalog teknologi.
        $php = Technology::create([
            'name' => 'PHP',
            'slug' => 'php',
            'category' => 'backend_language',
            'vendor' => 'PHP Group',
            'is_active' => true,
        ]);

        // Buat signature identifikasi PHP.
        $php->signatures()->create([
            'name' => 'PHP Header Detection',
            'type' => 'header',
            'target' => 'x-powered-by',
            'pattern' => 'PHP/',
            'match_type' => 'contains',
            'version_pattern' => '/PHP\/([0-9.]+)/i',
            'confidence' => 95,
            'is_active' => true,
        ]);

        // Simulasikan respons HTTP 200.
        $inspector = $this->fakeInspector([
            [
                'status_code' => 200,

                'headers' => [
                    'content-type' => [
                        'text/html; charset=utf-8',
                    ],

                    'x-powered-by' => [
                        'PHP/8.3.2',
                    ],
                ],

                'body' => '
                    <html>
                        <head>
                            <title>Website OPD</title>
                        </head>
                        <body>
                            <h1>Selamat Datang</h1>
                        </body>
                    </html>
                ',
            ],
        ]);

        $this->app->instance(
            HttpInspector::class,
            $inspector
        );

        // Jalankan pemeriksaan.
        $scan = app(WebsiteScanService::class)
            ->scan($website);

        // Memastikan pemeriksaan selesai.
        $this->assertSame('completed', $scan->status);

        $this->assertSame(
            200,
            $scan->http_status_code
        );

        $this->assertNotNull($scan->started_at);
        $this->assertNotNull($scan->completed_at);

        // Memastikan informasi website tersimpan.
        $this->assertSame(
            'Website OPD',
            $scan->page_title
        );

        $this->assertSame(
            'https://example.com',
            $scan->final_url
        );

        // Memastikan metadata tersimpan.
        $this->assertSame(
            'text/html; charset=utf-8',
            $scan->metadata['content_type']
        );

        // Memastikan PHP berhasil dikenali.
        $this->assertCount(
            1,
            $scan->scanTechnologies
        );

        $detected = $scan->scanTechnologies->first();

        $this->assertSame(
            $php->id,
            $detected->technology_id
        );

        $this->assertSame(
            '8.3.2',
            $detected->version
        );

        $this->assertSame(
            95,
            $detected->confidence
        );
    }

    public function test_http_404_is_still_a_completed_scan(): void
    {
        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Tidak Ditemukan',
            'url' => 'https://example.com/missing',
        ]);

        // Simulasi respons HTTP 404.
        $inspector = $this->fakeInspector([
            [
                'status_code' => 404,

                'headers' => [
                    'content-type' => [
                        'text/html',
                    ],
                ],

                'body' => '
                    <html>
                        <head>
                            <title>Not Found</title>
                        </head>
                        <body>Page not found</body>
                    </html>
                ',
            ],
        ]);

        $this->app->instance(
            HttpInspector::class,
            $inspector
        );

        $scan = app(WebsiteScanService::class)
            ->scan($website);

        $this->assertSame('completed', $scan->status);

        $this->assertSame(
            404,
            $scan->http_status_code
        );

        $this->assertSame(
            'Not Found',
            $scan->page_title
        );
    }

    public function test_private_ip_website_scan_is_recorded_as_failed(): void
    {
        $opd = Opd::create([
            'name' => 'Dinas Pengujian',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Internal',
            'url' => 'http://127.0.0.1',
        ]);

        // Gunakan validator asli, bukan simulasi.
        $inspector = new HttpInspector(
            new PublicUrlValidator()
        );

        $this->app->instance(
            HttpInspector::class,
            $inspector
        );

        $scan = app(WebsiteScanService::class)
            ->scan($website);

        // Pemeriksaan harus gagal dengan aman.
        $this->assertSame('failed', $scan->status);

        $this->assertNotNull($scan->error_message);

        $this->assertStringContainsString(
            'privat atau tidak aman',
            $scan->error_message
        );

        // Tidak boleh ada teknologi yang dicatat.
        $this->assertCount(
            0,
            $scan->scanTechnologies
        );
    }
}
