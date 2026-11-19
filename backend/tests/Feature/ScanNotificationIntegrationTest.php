<?php

namespace Tests\Feature;

use App\Jobs\RunWebsiteScanJob;
use App\Models\Scan;
use App\Models\Opd;
use App\Models\Technology;
use App\Models\User;
use App\Notifications\SimontaEventNotification;
use App\Services\HttpInspector;
use App\Services\PublicUrlValidator;
use App\Services\ScanNotificationService;
use App\Services\WebsiteScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ScanNotificationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membuat HTTP Inspector simulasi tanpa request internet.
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

                if (! is_array($response)) {
                    throw new RuntimeException(
                        'Respons simulasi tidak tersedia.'
                    );
                }

                return $response;
            }
        };
    }

    /**
     * Membuat OPD dan website khusus pengujian.
     */
    private function createTestWebsite(
        string $url = 'https://example.com'
    ) {
        $opd = Opd::create([
            'name' => 'OPD Notification Test',
        ]);

        return $opd->websites()->create([
            'name' => 'Website Notification Test',
            'url' => $url,
            'is_active' => true,
            'monitoring_enabled' => false,
        ]);
    }

    /**
     * Menyiapkan respons HTTP simulasi.
     */
    private function response(
        int $status,
        array $headers,
        string $body = '<html><head><title>Notification Test</title></head><body>Test</body></html>'
    ): array {
        return [
            'status_code' => $status,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    public function test_new_technology_generates_notification_after_scan(): void
    {
        $user = User::factory()->create();

        $website = $this->createTestWebsite();

        // Buat teknologi Cloudflare.
        $cloudflare = Technology::create([
            'name' => 'Cloudflare',
            'slug' => 'cloudflare',
            'category' => 'cdn_proxy',
            'vendor' => 'Cloudflare',
            'is_active' => true,
        ]);

        $cloudflare->signatures()->create([
            'name' => 'Cloudflare Server Header Test',
            'type' => 'header',
            'target' => 'server',
            'pattern' => 'cloudflare',
            'match_type' => 'contains',
            'confidence' => 95,
            'is_active' => true,
        ]);

        // Scan pertama: baseline, belum ada Cloudflare.
        // Scan kedua: Cloudflare baru terdeteksi.
        $inspector = $this->fakeInspector([
            $this->response(200, [
                'content-type' => ['text/html'],
                'server' => ['Apache/2.4.41'],
            ]),

            $this->response(200, [
                'content-type' => ['text/html'],
                'server' => ['cloudflare'],
            ]),
        ]);

        $this->app->instance(
            HttpInspector::class,
            $inspector
        );

        $scanService = app(WebsiteScanService::class);

        $baselineScan = $scanService->scan($website);
        $currentScan = $scanService->scan($website);

        $this->assertSame('completed', $baselineScan->status);
        $this->assertSame('completed', $currentScan->status);

        // Pastikan teknologi baru benar-benar dicatat.
        $this->assertDatabaseHas('technology_changes', [
            'scan_id' => $currentScan->id,
            'technology_id' => $cloudflare->id,
            'change_type' => 'added',
        ]);

        // Pastikan notifikasi dibuat.
        $this->assertSame(
            1,
            $user->notifications()->count()
        );

        $notification = $user->notifications()->firstOrFail();

        $this->assertSame(
            'technology_added',
            $notification->data['type']
        );

        $this->assertSame(
            'cloudflare',
            $notification->data['technology_slug']
        );

        // Pemanggilan ulang tidak boleh membuat duplikat.
        app(ScanNotificationService::class)
            ->notifyForScan(
                $currentScan->fresh([
                    'website',
                    'technologyChanges.technology',
                ])
            );

        $this->assertSame(
            1,
            $user->notifications()->count()
        );
    }

    public function test_http_404_generates_non_success_notification(): void
    {
        $user = User::factory()->create();

        $website = $this->createTestWebsite(
            'https://example.com/missing'
        );

        $inspector = $this->fakeInspector([
            $this->response(
                404,
                [
                    'content-type' => ['text/html'],
                ],
                '<html><head><title>Not Found</title></head><body>Page not found</body></html>'
            ),
        ]);

        $this->app->instance(
            HttpInspector::class,
            $inspector
        );

        $scan = app(WebsiteScanService::class)
            ->scan($website);

        // HTTP 404 tetap merupakan scan yang berhasil menerima respons.
        $this->assertSame('completed', $scan->status);
        $this->assertSame(404, $scan->http_status_code);

        $this->assertSame(
            1,
            $user->notifications()->count()
        );

        $notification = $user->notifications()->firstOrFail();

        $this->assertSame(
            'http_non_success',
            $notification->data['type']
        );

        $this->assertSame(
            404,
            $notification->data['http_status_code']
        );
    }

    public function test_failed_scan_generates_failure_notification(): void
    {
        $user = User::factory()->create();

        $website = $this->createTestWebsite(
            'http://127.0.0.1'
        );

        // Validator asli akan menolak alamat IP private.
        // Tidak ada request HTTP yang dikirim.
        $inspector = new HttpInspector(
            new PublicUrlValidator()
        );

        $this->app->instance(
            HttpInspector::class,
            $inspector
        );

        $scan = app(WebsiteScanService::class)
            ->scan($website);

        $this->assertSame('failed', $scan->status);
        $this->assertNotNull($scan->error_message);

        $this->assertSame(
            1,
            $user->notifications()->count()
        );

        $notification = $user->notifications()->firstOrFail();

        $this->assertSame(
            'scan_failed',
            $notification->data['type']
        );

        $this->assertSame(
            $scan->id,
            $notification->data['scan_id']
        );
    }

public function test_queue_job_failure_generates_failure_notification(): void
{
    $user = User::factory()->create();

    $website = $this->createTestWebsite();

    // Buat scan yang masih berada dalam antrean.
    $scan = $website->scans()->create([
        'status' => 'queued',
        'scheduled_for' => now(),
    ]);

    $job = new RunWebsiteScanJob($scan->id);

    $exception = new RuntimeException(
        'Queue worker timeout during website scan.'
    );

    // Simulasikan callback failed dari Laravel Queue Worker.
    $job->failed($exception);

    $scan->refresh();

    // Status scan harus berubah menjadi failed.
    $this->assertSame('failed', $scan->status);

    $this->assertNotNull($scan->completed_at);

    $this->assertStringContainsString(
        'Queue worker timeout',
        $scan->error_message
    );

    // Notifikasi kegagalan harus dikirim.
    $this->assertSame(
        1,
        $user->notifications()->count()
    );

    $notification = $user->notifications()->firstOrFail();

    $this->assertSame(
        'scan_failed',
        $notification->data['type']
    );

    $this->assertSame(
        $scan->id,
        $notification->data['scan_id']
    );

    // Pemanggilan callback ulang tidak boleh membuat duplikat.
    $job->failed($exception);

    $this->assertSame(
        1,
        $user->notifications()->count()
    );
}

public function test_removed_and_version_changed_technologies_generate_notifications(): void
{
    $user = User::factory()->create();

    $website = $this->createTestWebsite();

    // Buat teknologi PHP.
    $php = Technology::create([
        'name' => 'PHP',
        'slug' => 'php',
        'category' => 'backend_language',
        'vendor' => 'PHP Group',
        'is_active' => true,
    ]);

    $php->signatures()->create([
        'name' => 'PHP Header Notification Test',
        'type' => 'header',
        'target' => 'x-powered-by',
        'pattern' => 'PHP/',
        'match_type' => 'contains',
        'version_pattern' => '/PHP\/([0-9.]+)/i',
        'confidence' => 95,
        'is_active' => true,
    ]);

    // Buat teknologi Apache.
    $apache = Technology::create([
        'name' => 'Apache',
        'slug' => 'apache',
        'category' => 'web_server',
        'vendor' => 'Apache Software Foundation',
        'is_active' => true,
    ]);

    $apache->signatures()->create([
        'name' => 'Apache Header Notification Test',
        'type' => 'header',
        'target' => 'server',
        'pattern' => 'Apache',
        'match_type' => 'contains',
        'version_pattern' => '/Apache\/([0-9]+(?:\.[0-9]+){1,2})/i',
        'confidence' => 95,
        'is_active' => true,
    ]);

    $inspector = $this->fakeInspector([
        // Scan pertama: PHP dan Apache terdeteksi.
        $this->response(200, [
            'content-type' => ['text/html'],
            'x-powered-by' => ['PHP/8.3.2'],
            'server' => ['Apache/2.4.41'],
        ]),

        // Scan kedua: PHP hilang dan versi Apache berubah.
        $this->response(200, [
            'content-type' => ['text/html'],
            'server' => ['Apache/2.4.58'],
        ]),
    ]);

    $this->app->instance(
        HttpInspector::class,
        $inspector
    );

    $scanService = app(WebsiteScanService::class);

    $baselineScan = $scanService->scan($website);
    $currentScan = $scanService->scan($website);

    $this->assertSame('completed', $baselineScan->status);
    $this->assertSame('completed', $currentScan->status);

    // Pastikan PHP tercatat sebagai Removed.
    $this->assertDatabaseHas('technology_changes', [
        'scan_id' => $currentScan->id,
        'technology_id' => $php->id,
        'change_type' => 'removed',
        'previous_version' => '8.3.2',
        'current_version' => null,
    ]);

    // Pastikan Apache tercatat sebagai Version Changed.
    $this->assertDatabaseHas('technology_changes', [
        'scan_id' => $currentScan->id,
        'technology_id' => $apache->id,
        'change_type' => 'version_changed',
        'previous_version' => '2.4.41',
        'current_version' => '2.4.58',
    ]);

    // Dua perubahan harus menghasilkan dua notifikasi.
    $this->assertSame(
        2,
        $user->notifications()->count()
    );

    $notificationTypes = $user->notifications()
        ->get()
        ->pluck('data.type')
        ->all();

    $this->assertContains(
        'technology_removed',
        $notificationTypes
    );

    $this->assertContains(
        'technology_version_changed',
        $notificationTypes
    );

    $versionNotification = $user->notifications()
        ->where('data->type', 'technology_version_changed')
        ->firstOrFail();

    $this->assertSame(
        '2.4.41',
        $versionNotification->data['previous_version']
    );

    $this->assertSame(
        '2.4.58',
        $versionNotification->data['current_version']
    );

    $removedNotification = $user->notifications()
        ->where('data->type', 'technology_removed')
        ->firstOrFail();

    $this->assertSame(
        'php',
        $removedNotification->data['technology_slug']
    );
}

}
