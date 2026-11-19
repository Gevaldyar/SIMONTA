<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\WebsiteScanService;
use App\Services\ScanNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Throwable;

#[Timeout(45)]
#[FailOnTimeout]
class RunWebsiteScanJob implements ShouldQueue
{
    use Queueable;

    // Hanya satu kali percobaan.
    public int $tries = 1;

    public function __construct(
        public int $scanId
    ) {}

    /**
     * Menjalankan pemeriksaan website di background.
     */
    public function handle(
        WebsiteScanService $scanService
    ): void {
        $scan = Scan::find($this->scanId);

        // Abaikan job jika scan sudah tidak tersedia
        // atau tidak lagi menunggu diproses.
        if (!$scan || $scan->status !== 'queued') {
            return;
        }

        $scanService->process($scan);
    }

    /**
     * Menangani kegagalan pada tingkat Queue Worker.
     */
    public function failed(Throwable $exception): void
    {
        report($exception);

        /*
         * Ubah status hanya jika masih queued atau running.
         * Conditional update mencegah callback terlambat
         * mengubah scan yang sudah completed.
         */
        $affected = Scan::query()
            ->whereKey($this->scanId)
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => substr(
                    $exception->getMessage(),
                    0,
                    1000
                ),
                'updated_at' => now(),
            ]);

        // Scan sudah selesai, gagal, atau tidak ditemukan.
        if ($affected !== 1) {
            return;
        }

        $scan = Scan::find($this->scanId);

        if (!$scan) {
            return;
        }

        // Kirim notifikasi kegagalan.
        app(ScanNotificationService::class)
            ->notifyForScan($scan);
    }
}
