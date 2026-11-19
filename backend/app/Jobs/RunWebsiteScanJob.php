<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Services\WebsiteScanService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

use App\Services\ScanNotificationService;

class RunWebsiteScanJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

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

        // Abaikan job jika scan tidak tersedia atau
        // sudah tidak berada dalam antrean.
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

        $scan = Scan::find($this->scanId);

        if (
            !$scan
            || !in_array($scan->status, ['queued', 'running'], true)
        ) {
            return;
        }

        $scan->forceFill([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => substr(
                $exception->getMessage(),
                0,
                1000
            ),
        ])->save();
        app(ScanNotificationService::class)
    ->notifyForScan($scan->fresh());
    }
}
