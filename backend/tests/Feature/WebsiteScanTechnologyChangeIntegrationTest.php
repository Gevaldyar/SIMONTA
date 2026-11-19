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

class WebsiteScanTechnologyChangeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * HTTP Inspector simulasi dengan urutan respons yang sudah ditentukan.
     * Tidak melakukan request ke website eksternal.
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
                        'Respons HTTP simulasi tidak tersedia.'
                    );
                }

                return $response;
            }
        };
    }

    private function createTechnologyWithHeaderSignature(
        string $name,
        string $slug,
        string $category,
        string $signatureName,
        string $header,
        string $pattern,
        ?string $versionPattern = null,
        int $confidence = 95,
        string $matchType = 'contains'
    ): Technology {
        $technology = Technology::create([
            'name' => $name,
            'slug' => $slug,
            'category' => $category,
            'vendor' => $name,
            'is_active' => true,
        ]);

        $technology->signatures()->create([
            'name' => $signatureName,
            'type' => 'header',
            'target' => $header,
            'pattern' => $pattern,
            'match_type' => $matchType,
            'version_pattern' => $versionPattern,
            'confidence' => $confidence,
            'is_active' => true,
        ]);

        return $technology;
    }

    public function test_full_website_scan_flow_records_added_removed_and_version_changed(): void
    {
        $opd = Opd::create([
            'name' => 'Dinas Pengujian Integrasi',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Integrasi',
            'url' => 'https://example.com',
        ]);

        $php = $this->createTechnologyWithHeaderSignature(
            name: 'PHP',
            slug: 'php',
            category: 'backend_language',
            signatureName: 'PHP Header Detection',
            header: 'x-powered-by',
            pattern: 'PHP/',
            versionPattern: '/PHP\/([0-9.]+)/i'
        );

        $apache = $this->createTechnologyWithHeaderSignature(
            name: 'Apache',
            slug: 'apache',
            category: 'web_server',
            signatureName: 'Apache Server Header Detection',
            header: 'server',
            pattern: 'Apache/',
            versionPattern: '/Apache\/([0-9.]+)/i'
        );

        $cloudflare = $this->createTechnologyWithHeaderSignature(
            name: 'Cloudflare',
            slug: 'cloudflare',
            category: 'cdn_proxy',
            signatureName: 'Cloudflare Cache Status Detection',
            header: 'cf-cache-status',
            pattern: '/^(?:HIT|MISS|DYNAMIC|BYPASS|EXPIRED|REVALIDATED|STALE|UPDATING|NONE)$/i',
            confidence: 92,
            matchType: 'regex'
        );

        $inspector = $this->fakeInspector([
            // Scan pertama: PHP dan Apache versi 2.4.41 terdeteksi.
            [
                'status_code' => 200,
                'headers' => [
                    'content-type' => ['text/html; charset=utf-8'],
                    'x-powered-by' => ['PHP/8.3.2'],
                    'server' => ['Apache/2.4.41'],
                ],
                'body' => '<html><head><title>Website Integrasi</title></head><body>Baseline</body></html>',
            ],

            // Scan kedua: PHP hilang, Apache berubah versi, Cloudflare ditambahkan.
            [
                'status_code' => 200,
                'headers' => [
                    'content-type' => ['text/html; charset=utf-8'],
                    'server' => ['Apache/2.4.58'],
                    'cf-cache-status' => ['DYNAMIC'],
                ],
                'body' => '<html><head><title>Website Integrasi</title></head><body>Updated</body></html>',
            ],
        ]);

        $this->app->instance(HttpInspector::class, $inspector);

        // Pemindaian pertama harus menjadi baseline.
        $firstScan = app(WebsiteScanService::class)->scan($website);

        $this->assertSame('completed', $firstScan->status);
        $this->assertSame(200, $firstScan->http_status_code);
        $this->assertCount(2, $firstScan->scanTechnologies);
        $this->assertCount(0, $firstScan->technologyChanges);
        $this->assertDatabaseCount('technology_changes', 0);

        // Pastikan scan kedua dimulai setelah baseline selesai.
        $this->travel(2)->seconds();

        // Pemindaian kedua melewati alur WebsiteScanService yang sebenarnya.
        $secondScan = app(WebsiteScanService::class)->scan($website);

        $this->assertSame('completed', $secondScan->status);
        $this->assertSame(200, $secondScan->http_status_code);
        $this->assertCount(
    2,
    $secondScan->scanTechnologies,
    'Teknologi terdeteksi: ' .
    $secondScan->scanTechnologies
        ->map(fn ($item) => $item->technology?->name)
        ->implode(', ') .
    '. Header fingerprint: ' .
    json_encode($secondScan->metadata['headers'] ?? [])
);
        $this->assertCount(3, $secondScan->technologyChanges);

        $changes = $secondScan->technologyChanges->keyBy('change_type');

        // Cloudflare ditemukan pada scan kedua: ADDED.
        $added = $changes->get('added');
        $this->assertNotNull($added);
        $this->assertSame($cloudflare->id, $added->technology_id);
        $this->assertSame('Cloudflare', $added->technology_name);
        $this->assertSame($firstScan->id, $added->previous_scan_id);
        $this->assertNull($added->previous_version);
        $this->assertNotNull($added->current_evidence);

        // PHP tidak lagi ditemukan pada scan kedua: REMOVED.
        $removed = $changes->get('removed');
        $this->assertNotNull($removed);
        $this->assertSame($php->id, $removed->technology_id);
        $this->assertSame('PHP', $removed->technology_name);
        $this->assertSame('8.3.2', $removed->previous_version);
        $this->assertNull($removed->current_version);
        $this->assertNotNull($removed->previous_evidence);

        // Versi Apache berubah: VERSION_CHANGED.
        $versionChanged = $changes->get('version_changed');
        $this->assertNotNull($versionChanged);
        $this->assertSame($apache->id, $versionChanged->technology_id);
        $this->assertSame('2.4.41', $versionChanged->previous_version);
        $this->assertSame('2.4.58', $versionChanged->current_version);
        $this->assertSame($firstScan->id, $versionChanged->previous_scan_id);
        $this->assertNotNull($versionChanged->previous_evidence);
        $this->assertNotNull($versionChanged->current_evidence);

        $this->assertDatabaseCount('technology_changes', 3);
    }
}
