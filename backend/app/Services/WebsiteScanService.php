<?php

namespace App\Services;

use App\Models\Opd;
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
     * Mengambil pekerjaan secara atomik dan menjalankan pemeriksaan.
     */
    public function process(Scan $scan): Scan
    {
        $scanId = (int) $scan->getKey();
        $websiteId = (int) $scan->website_id;

        /*
         * ATOMIC CLAIM
         *
         * Urutan penguncian:
         * 1. Website
         * 2. Scan melalui conditional update
         *
         * Urutan ini mengikuti pola penguncian scheduler
         * dan ScanController agar mengurangi risiko deadlock.
         */
        $claim = DB::transaction(function () use (
            $scanId,
            $websiteId
        ): array {
            $website = Website::withTrashed()
                ->whereKey($websiteId)
                ->lockForUpdate()
                ->first();

            $opd = $website
                ? Opd::withTrashed()
                    ->whereKey($website->opd_id)
                    ->first()
                : null;

            $websiteIsAvailable =
                $website !== null
                && !$website->trashed()
                && $website->is_active
                && $opd !== null
                && !$opd->trashed()
                && $opd->is_active;

            // Website atau OPD sudah tidak tersedia.
            // Akhiri scan agar tidak tertinggal dalam antrean.
            if (!$websiteIsAvailable) {
                $affected = Scan::query()
                    ->whereKey($scanId)
                    ->where('status', 'queued')
                    ->update([
                        'status' => 'failed',
                        'completed_at' => now(),
                        'error_message' =>
                            'Pemeriksaan dibatalkan karena website atau OPD sudah tidak aktif atau tidak tersedia.',
                    ]);

                return [
                    'claimed' => false,
                    'terminalized' => $affected === 1,
                ];
            }

            /*
             * Conditional update merupakan pengaman utama.
             *
             * Worker pertama yang berhasil mengubah queued
             * menjadi running menjadi satu-satunya worker
             * yang boleh menjalankan HTTP request.
             *
             * Worker lain mendapatkan affected rows = 0.
             */
            $affected = Scan::query()
                ->whereKey($scanId)
                ->where('status', 'queued')
                ->update([
                    'status' => 'running',
                    'started_at' => now(),
                    'completed_at' => null,
                    'error_message' => null,
                ]);

            return [
    'claimed' => $affected === 1,
    'terminalized' => false,
    'website_url' => $website->url,
];
        }, 3);

        // Scan tidak berhasil diambil.
        // Jangan menjalankan HTTP request kedua kali.
        if (!$claim['claimed']) {
            $scan = $scan->fresh([
                'website',
                'scanTechnologies.technology',
                'technologyChanges.technology',
            ]) ?? $scan;

            // Notifikasi hanya jika scan baru saja diterminasi
            // karena website atau OPD sudah tidak aktif.
            if ($claim['terminalized']) {
                $this->notificationService->notifyForScan($scan);
            }

            return $scan;
        }

        // Scan berhasil diklaim oleh worker ini.
        $scan = Scan::query()->findOrFail($scanId);

        try {
            // 1. Periksa website menggunakan HTTP GET.
            $fingerprint = $this->httpInspector->inspect(
                $claim['website_url']
            );

            // 2. Simpan hasil dan perubahan teknologi.
            DB::transaction(function () use (
                $scan,
                $fingerprint
            ): void {
                $this->detectionService->detect(
                    $scan,
                    $fingerprint
                );

                $pageTitle = $fingerprint['page_title'] ?? null;

                if (is_string($pageTitle)) {
                    $pageTitle = substr($pageTitle, 0, 255);
                }

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
                $this->changeDetectionService->record(
                    $scan->fresh()
                );
            }, 3);

        } catch (Throwable $exception) {
            report($exception);

            // Simpan kegagalan pemeriksaan.
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

        // 4. Kirim notifikasi setelah pemeriksaan selesai.
        $this->notificationService->notifyForScan($scan);

        return $scan;
    }
}
