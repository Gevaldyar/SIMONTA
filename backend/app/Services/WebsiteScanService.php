<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class WebsiteScanService
{
    public function __construct(
        private readonly HttpInspector $httpInspector,
        private readonly TechnologyDetectionService $detectionService,
        private readonly TechnologyChangeDetectionService $changeDetectionService,
        private readonly ScanNotificationService $notificationService

    ) {}

    /**
     * Membuat dan langsung menjalankan pemeriksaan website.
     */
    public function scan(Website $website): Scan
    {
        if (!$website->is_active) {
            throw new InvalidArgumentException(
                'Website tidak aktif dan tidak dapat diperiksa.'
            );
        }

        $scan = $website->scans()->create([
            'status' => 'queued',
            'scheduled_for' => now(),
        ]);

        return $this->process($scan);
    }

    /**
     * Menjalankan pemeriksaan dari scan yang sudah dibuat.
     */
    public function process(Scan $scan): Scan
    {
        $scan->loadMissing('website');

        if ($scan->status !== 'queued') {
            throw new InvalidArgumentException(
                'Hanya scan dengan status queued yang dapat diproses.'
            );
        }

        $scan->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
        ])->save();

        try {
            // 1. Ambil fingerprint website.
            $fingerprint = $this->httpInspector->inspect(
                $scan->website->url
            );

            // 2. Simpan hasil deteksi, status scan, dan perubahan
            // teknologi dalam satu transaksi database.
            DB::transaction(function () use (
                $scan,
                $fingerprint
            ) {
                // Identifikasi teknologi.
                $this->detectionService->detect(
                    $scan,
                    $fingerprint
                );

                $pageTitle = $fingerprint['page_title'] ?? null;

                if (is_string($pageTitle)) {
                    $pageTitle = substr($pageTitle, 0, 255);
                }

                // Tandai scan selesai terlebih dahulu.
                $scan->forceFill([
                    'status' => 'completed',
                    'completed_at' => now(),

                    'http_status_code' =>
                        $fingerprint['status_code'],

                    'response_time_ms' =>
                        $fingerprint['response_time_ms'],

                    'final_url' =>
                        $fingerprint['final_url'],

                    'page_title' => $pageTitle,

                    'error_message' => null,

                    'metadata' => [
                        'content_type' =>
                            $fingerprint['content_type'],

                        'body_size_bytes' =>
                            $fingerprint['body_size_bytes'],

                        'redirect_count' =>
                            $fingerprint['redirect_count'],

                        'headers' =>
                            $fingerprint['headers'],
                    ],
                ])->save();

                // 3. Bandingkan dengan scan sebelumnya.
                // Method ini tidak membuat catatan untuk scan pertama.
                $this->changeDetectionService->record(
                    $scan->fresh()
                );
            }, 3);

        } catch (Throwable $exception) {
            // Catat detail teknis di log server.
            report($exception);

            // Simpan kegagalan pada riwayat pemeriksaan.
            $scan->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => substr(
                    $exception->getMessage(),
                    0,
                    1000
                ),
            ])->save();
        }

        $scan = $scan->fresh([
            'website',
            'scanTechnologies.technology',
            'technologyChanges.technology',
        ]);

        // Kirim notifikasi setelah proses scan selesai.
        $this->notificationService->notifyForScan($scan);

        return $scan;
    }
}
