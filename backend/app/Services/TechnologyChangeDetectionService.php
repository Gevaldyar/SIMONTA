<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\ScanTechnology;
use App\Models\TechnologyChange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TechnologyChangeDetectionService
{
    /**
     * Membandingkan hasil scan terbaru dengan scan completed
     * sebelumnya yang selesai sebelum scan terbaru dimulai.
     */
    public function record(Scan $scan): Collection
    {
        if (
    $scan->status !== 'completed'
    || !$scan->started_at
    || $scan->http_status_code < 200
    || $scan->http_status_code >= 300
) {
    return collect();
}

        return DB::transaction(function () use ($scan) {
            // Cari baseline yang benar-benar lebih dahulu selesai.
            $previousScan = Scan::query()
    ->where('website_id', $scan->website_id)
    ->where('status', 'completed')
    ->whereBetween('http_status_code', [200, 299])
    ->whereNotNull('completed_at')
    ->where('completed_at', '<=', $scan->started_at)
    ->where('id', '!=', $scan->id)
    ->orderByDesc('completed_at')
    ->orderByDesc('id')
    ->first();

            // Membuat proses idempotent apabila method
            // dipanggil ulang untuk scan yang sama.
            $scan->technologyChanges()->delete();

            // Scan pertama menjadi baseline.
            if (!$previousScan) {
                return collect();
            }

            $currentResults = $scan->scanTechnologies()
                ->with('technology')
                ->get()
                ->keyBy(
                    fn (ScanTechnology $item) =>
                        (string) $item->technology_id
                );

            $previousResults = $previousScan->scanTechnologies()
                ->with('technology')
                ->get()
                ->keyBy(
                    fn (ScanTechnology $item) =>
                        (string) $item->technology_id
                );

            $changes = collect();

            // 1. Teknologi baru atau versi berubah.
            foreach ($currentResults as $technologyId => $current) {
                $previous = $previousResults->get($technologyId);

                // Teknologi tidak ditemukan pada scan sebelumnya.
                if (!$previous) {
                    $changes->push(
                        $this->createChange(
                            scan: $scan,
                            previousScan: $previousScan,
                            current: $current,
                            previous: null,
                            changeType: 'added'
                        )
                    );

                    continue;
                }

                // Bandingkan versi hanya jika kedua versi tersedia.
                $previousVersion = $this->normalizeVersion(
                    $previous->version
                );

                $currentVersion = $this->normalizeVersion(
                    $current->version
                );

                if (
                    $previousVersion !== null
                    && $currentVersion !== null
                    && $previousVersion !== $currentVersion
                ) {
                    $changes->push(
                        $this->createChange(
                            scan: $scan,
                            previousScan: $previousScan,
                            current: $current,
                            previous: $previous,
                            changeType: 'version_changed'
                        )
                    );
                }
            }

            // 2. Teknologi yang sudah tidak ditemukan.
            foreach ($previousResults as $technologyId => $previous) {
                if ($currentResults->has($technologyId)) {
                    continue;
                }

                $changes->push(
                    $this->createChange(
                        scan: $scan,
                        previousScan: $previousScan,
                        current: null,
                        previous: $previous,
                        changeType: 'removed'
                    )
                );
            }

            return $changes;
        }, 3);
    }

    /**
     * Membuat catatan perubahan berdasarkan snapshot
     * hasil deteksi sebelum dan sesudah.
     */
    private function createChange(
        Scan $scan,
        Scan $previousScan,
        ?ScanTechnology $current,
        ?ScanTechnology $previous,
        string $changeType
    ): TechnologyChange {
        $technology = $current?->technology
            ?? $previous?->technology;

        return $scan->technologyChanges()->create([
            'previous_scan_id' => $previousScan->id,

            'technology_id' => $technology->id,
            'technology_name' => $technology->name,
            'technology_slug' => $technology->slug,

            'change_type' => $changeType,

            'previous_version' => $previous?->version,
            'previous_confidence' => $previous?->confidence,
            'previous_evidence' => $previous?->evidence,

            'current_version' => $current?->version,
            'current_confidence' => $current?->confidence,
            'current_evidence' => $current?->evidence,
        ]);
    }

    /**
     * Menghindari perbandingan versi kosong atau berisi spasi.
     */
    private function normalizeVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        $version = trim($version);

        return $version === '' ? null : $version;
    }
}