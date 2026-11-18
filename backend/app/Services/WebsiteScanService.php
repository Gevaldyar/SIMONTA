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
        private readonly TechnologyDetectionService $detectionService
    ) {}

    /**
     * Membuat dan langsung menjalankan pemeriksaan website.
     *
     * Untuk sekarang proses berjalan secara synchronous.
     * Nantinya proses ini dapat dipanggil melalui Queue Job.
     */
    public function scan(Website $website): Scan
    {
        if (!$website->is_active) {
            throw new InvalidArgumentException(
                'Website tidak aktif dan tidak dapat diperiksa.'
            );
        }

        // Buat riwayat pemeriksaan baru.
        $scan = $website->scans()->create([
            'status' => 'queued',
            'scheduled_for' => now(),
        ]);

        return $this->process($scan);
    }

    /**
     * Menjalankan pemeriksaan dari sebuah scan yang sudah dibuat.
     */
    public function process(Scan $scan): Scan
    {
        $scan->loadMissing('website');

        if ($scan->status !== 'queued') {
            throw new InvalidArgumentException(
                'Hanya scan dengan status queued yang dapat diproses.'
            );
        }

        // Tandai bahwa pemeriksaan sudah dimulai.
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

            // 2. Simpan hasil deteksi dan status scan
            // dalam satu transaksi database.
            DB::transaction(function () use (
                $scan,
                $fingerprint
            ) {
                // Jalankan mesin identifikasi teknologi.
                $this->detectionService->detect(
                    $scan,
                    $fingerprint
                );

                $pageTitle = $fingerprint['page_title'] ?? null;

                // Kolom page_title di database menggunakan
                // panjang default VARCHAR(255).
                if (is_string($pageTitle)) {
                    $pageTitle = substr($pageTitle, 0, 255);
                }

                // Simpan informasi pemeriksaan.
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

        // Kembalikan scan beserta hasil identifikasinya.
        return $scan->fresh([
            'website',
            'scanTechnologies.technology',
        ]);
    }
}
