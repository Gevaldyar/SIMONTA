<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\Website;
use App\Models\Scan;
use App\Models\TechnologyChange;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TechnologyChangeDemoCleanupSeeder extends Seeder
{
    private const OPD_CODE = 'SIMONTA-DEMO-TECH-CHANGE';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'Cleanup simulasi hanya boleh dijalankan di environment local.'
            );
        }

        $opd = Opd::withTrashed()
            ->where('code', self::OPD_CODE)
            ->first();

        if (! $opd) {
            $this->command?->info('Data simulasi tidak ditemukan. Tidak ada yang dihapus.');
            return;
        }

        DB::transaction(function () use ($opd) {
            $websiteIds = Website::withTrashed()
                ->where('opd_id', $opd->id)
                ->pluck('id');

            $scanIds = Scan::query()
                ->whereIn('website_id', $websiteIds)
                ->pluck('id');

            if ($scanIds->isNotEmpty()) {
                // Hapus hanya perubahan yang terkait scan milik OPD demo.
                TechnologyChange::query()
                    ->where(function ($query) use ($scanIds) {
                        $query->whereIn('scan_id', $scanIds)
                            ->orWhereIn('previous_scan_id', $scanIds);
                    })
                    ->delete();

                // Hapus hasil deteksi yang terkait scan demo jika ada.
                DB::table('scan_technologies')
                    ->whereIn('scan_id', $scanIds)
                    ->delete();

                // Hapus hanya scan dari website demo.
                Scan::query()
                    ->whereIn('id', $scanIds)
                    ->delete();
            }

            // Hapus website demo, termasuk jika sebelumnya soft-deleted.
            if ($websiteIds->isNotEmpty()) {
                Website::withTrashed()
                    ->whereIn('id', $websiteIds)
                    ->forceDelete();
            }

            // Hapus OPD demo saja.
            $opd->forceDelete();
        });

        $this->command?->info('Data simulasi Technology Changes berhasil dibersihkan.');
        $this->command?->info('Data OPD dan website lainnya tidak diubah.');
    }
}
