<?php

namespace App\Console\Commands;

use App\Jobs\RunWebsiteScanJob;
use App\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchScheduledWebsiteScans extends Command
{
    protected $signature = 'simonta:dispatch-scheduled-scans';

    protected $description =
        'Memasukkan website yang sudah jatuh tempo ke antrean monitoring SIMONTA.';

    public function handle(): int
    {
        $now = now();

        $queued = 0;
        $skipped = 0;

        // Ambil website aktif yang monitoring-nya diaktifkan
        // dan waktu pemeriksaannya sudah tiba.
        Website::query()
            ->select('id')
            ->where('is_active', true)
            ->where('monitoring_enabled', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('next_scan_at')
                    ->orWhere('next_scan_at', '<=', $now);
            })
            ->whereHas('opd', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('id')
            ->chunkById(100, function ($websites) use (
                &$queued,
                &$skipped,
                $now
            ) {
                foreach ($websites as $candidate) {
                    $scanId = DB::transaction(function () use (
                        $candidate,
                        $now
                    ) {
                        // Kunci data website dan periksa kembali
                        // kondisinya di dalam transaksi.
                        $website = Website::query()
                            ->whereKey($candidate->id)
                            ->where('is_active', true)
                            ->where('monitoring_enabled', true)
                            ->whereHas('opd', function ($query) {
                                $query->where('is_active', true);
                            })
                            ->lockForUpdate()
                            ->first();

                        if (!$website) {
                            return null;
                        }

                        $currentTime = now();

                        // Pastikan jadwal belum berubah
                        // sejak daftar kandidat diambil.
                        if (
                            $website->next_scan_at !== null
                            && $website->next_scan_at->isAfter($currentTime)
                        ) {
                            return null;
                        }

                        // Jangan membuat pemeriksaan ganda.
                        $hasPendingScan = $website->scans()
                            ->whereIn('status', ['queued', 'running'])
                            ->exists();

                        if ($hasPendingScan) {
                            return null;
                        }

                        // Jika belum pernah dijadwalkan,
                        // pemeriksaan pertama dianggap jatuh tempo sekarang.
                        $scheduledFor = $website->next_scan_at ?? $currentTime;

                        $scan = $website->scans()->create([
                            'status' => 'queued',
                            'scheduled_for' => $scheduledFor,
                        ]);

                        // Batasi interval ke rentang yang diizinkan.
                        $allowedIntervals = [1, 5, 10, 30];

$interval = (int) $website->monitoring_interval_minutes;

// Perlindungan jika terdapat data lama atau tidak valid.
if (! in_array($interval, $allowedIntervals, true)) {
    $interval = 30;
}

                        // Atur pemeriksaan otomatis selanjutnya.
                        $website->forceFill([
                            'next_scan_at' => $currentTime
                                ->copy()
                                ->addMinutes($interval),
                        ])->save();

                        // Dispatch hanya setelah transaksi berhasil commit.
                        RunWebsiteScanJob::dispatch($scan->id)
                            ->afterCommit();

                        return $scan->id;
                    }, 3);

                    if ($scanId !== null) {
                        $queued++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Website scan masuk antrean: {$queued}");
        $this->info("Website dilewati: {$skipped}");

        return self::SUCCESS;
    }
}
