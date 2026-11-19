<?php

namespace App\Console\Commands;

use App\Models\Scan;
use App\Services\ScanNotificationService;
use Illuminate\Console\Command;

class RecoverStaleWebsiteScans extends Command
{
    protected $signature = 'simonta:recover-stale-scans';

    protected $description =
        'Memulihkan pemeriksaan website yang terlalu lama berstatus running.';

    private const STALE_AFTER_MINUTES = 3;

    public function handle(
        ScanNotificationService $notificationService
    ): int {
        $cutoff = now()->subMinutes(
            self::STALE_AFTER_MINUTES
        );

        $recovered = 0;

        Scan::query()
            ->where('status', 'running')
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($scans) use (
                $cutoff,
                $notificationService,
                &$recovered
            ): void {
                foreach ($scans as $candidate) {
                    /*
                     * Conditional update:
                     * scan hanya dipulihkan jika masih running
                     * dan started_at masih melewati batas waktu.
                     *
                     * Ini mencegah scan yang sudah selesai
                     * tertimpa oleh proses recovery.
                     */
                    $recoveredAt = now();

                    $affected = Scan::query()
                        ->whereKey($candidate->id)
                        ->where('status', 'running')
                        ->whereNotNull('started_at')
                        ->where('started_at', '<=', $cutoff)
                        ->update([
                            'status' => 'failed',
                            'completed_at' => $recoveredAt,
                            'error_message' =>
                                'Scan dihentikan otomatis oleh sistem karena terlalu lama berstatus running. Worker mungkin berhenti atau mengalami timeout.',
                            'updated_at' => $recoveredAt,
                        ]);

                    // Scan sudah selesai atau sudah ditangani
                    // oleh proses lain.
                    if ($affected !== 1) {
                        continue;
                    }

                    $scan = Scan::query()
                        ->with('website')
                        ->find($candidate->id);

                    if ($scan) {
                        $notificationService->notifyForScan($scan);
                    }

                    $recovered++;
                }
            });

        $this->info(
            "Scan running yang dipulihkan: {$recovered}"
        );

        $this->info(
            "Batas pemulihan: " .
            self::STALE_AFTER_MINUTES .
            " menit."
        );

        return self::SUCCESS;
    }
}
