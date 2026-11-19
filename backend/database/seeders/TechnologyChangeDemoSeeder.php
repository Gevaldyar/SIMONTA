<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\Website;
use App\Models\Scan;
use App\Models\Technology;
use App\Models\TechnologyChange;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TechnologyChangeDemoSeeder extends Seeder
{
    private const OPD_CODE = 'SIMONTA-DEMO-TECH-CHANGE';

    public function run(): void
    {
        // Seeder demo hanya boleh dijalankan di environment local.
        if (! app()->environment('local')) {
            throw new RuntimeException(
                'Seeder simulasi hanya boleh dijalankan di environment local.'
            );
        }

        $requiredSlugs = ['php', 'apache', 'cloudflare'];

        $technologies = Technology::query()
            ->whereIn('slug', $requiredSlugs)
            ->get()
            ->keyBy('slug');

        $missing = array_values(array_diff(
            $requiredSlugs,
            $technologies->keys()->all()
        ));

        if (! empty($missing)) {
            throw new RuntimeException(
                'Teknologi belum tersedia di katalog: ' . implode(', ', $missing)
                . '. Periksa TechnologyCatalogSeeder terlebih dahulu.'
            );
        }

        if (Opd::withTrashed()->where('code', self::OPD_CODE)->exists()) {
            throw new RuntimeException(
                'Data simulasi sudah ada. Jalankan TechnologyChangeDemoCleanupSeeder '
                . 'sebelum membuat simulasi baru.'
            );
        }

        DB::transaction(function () use ($technologies) {
            // 1. Buat OPD khusus simulasi.
            $opd = Opd::create([
                'name' => '[DEMO] SIMONTA Technology Changes',
                'code' => self::OPD_CODE,
                'description' => 'Data simulasi untuk pengujian fitur Technology Changes.',
                'address' => 'Data simulasi lokal',
                'is_active' => true,
            ]);

            // 2. Buat website simulasi.
            // Domain .invalid sengaja digunakan agar bukan website sungguhan.
            $website = $opd->websites()->create([
                'name' => 'Demo Technology Change Website',
                'url' => 'https://technology-changes-demo.invalid',
                'description' => 'Website simulasi lokal, bukan target pemindaian.',
                'is_active' => true,
                'monitoring_enabled' => false,
                'monitoring_interval_minutes' => 60,
            ]);

            // 3. Buat waktu baseline dan scan terbaru.
            $baselineStartedAt = now()->subMinutes(30);
            $baselineCompletedAt = now()->subMinutes(29);

            $currentStartedAt = now()->subMinutes(5);
            $currentCompletedAt = now()->subMinutes(4);

            // 4. Buat scan pertama sebagai baseline.
            $baselineScan = Scan::create([
                'website_id' => $website->id,
                'status' => 'completed',
                'scheduled_for' => $baselineStartedAt,
                'started_at' => $baselineStartedAt,
                'completed_at' => $baselineCompletedAt,
                'http_status_code' => 200,
                'response_time_ms' => 420,
                'final_url' => $website->url,
                'page_title' => 'Demo Website - Baseline',
                'metadata' => [
                    'demo_fixture' => true,
                    'description' => 'Baseline scan untuk pengujian.',
                ],
            ]);

            $baselineScan->forceFill([
                'created_at' => $baselineCompletedAt,
                'updated_at' => $baselineCompletedAt,
            ])->save();

            // 5. Buat scan kedua sebagai kondisi terbaru.
            $currentScan = Scan::create([
                'website_id' => $website->id,
                'status' => 'completed',
                'scheduled_for' => $currentStartedAt,
                'started_at' => $currentStartedAt,
                'completed_at' => $currentCompletedAt,
                'http_status_code' => 200,
                'response_time_ms' => 350,
                'final_url' => $website->url,
                'page_title' => 'Demo Website - Current',
                'metadata' => [
                    'demo_fixture' => true,
                    'description' => 'Scan terbaru untuk pengujian.',
                ],
            ]);

            $currentScan->forceFill([
                'created_at' => $currentCompletedAt,
                'updated_at' => $currentCompletedAt,
            ])->save();

            // 6. Catat perubahan PHP: Removed.
            $this->createChange([
                'scan_id' => $currentScan->id,
                'previous_scan_id' => $baselineScan->id,
                'technology_id' => $technologies['php']->id,
                'technology_name' => $technologies['php']->name,
                'technology_slug' => $technologies['php']->slug,
                'change_type' => 'removed',
                'previous_version' => '8.3.2',
                'previous_confidence' => 95,
                'previous_evidence' => [
                    'source' => 'HTTP header',
                    'header' => 'x-powered-by',
                    'value' => 'PHP/8.3.2',
                ],
                'current_version' => null,
                'current_confidence' => null,
                'current_evidence' => null,
            ], $currentCompletedAt);

            // 7. Catat perubahan versi Apache.
            $this->createChange([
                'scan_id' => $currentScan->id,
                'previous_scan_id' => $baselineScan->id,
                'technology_id' => $technologies['apache']->id,
                'technology_name' => $technologies['apache']->name,
                'technology_slug' => $technologies['apache']->slug,
                'change_type' => 'version_changed',
                'previous_version' => '2.4.41',
                'previous_confidence' => 95,
                'previous_evidence' => [
                    'source' => 'HTTP header',
                    'header' => 'server',
                    'value' => 'Apache/2.4.41',
                ],
                'current_version' => '2.4.58',
                'current_confidence' => 95,
                'current_evidence' => [
                    'source' => 'HTTP header',
                    'header' => 'server',
                    'value' => 'Apache/2.4.58',
                ],
            ], $currentCompletedAt);

            // 8. Catat perubahan Cloudflare: Added.
            $this->createChange([
                'scan_id' => $currentScan->id,
                'previous_scan_id' => $baselineScan->id,
                'technology_id' => $technologies['cloudflare']->id,
                'technology_name' => $technologies['cloudflare']->name,
                'technology_slug' => $technologies['cloudflare']->slug,
                'change_type' => 'added',
                'previous_version' => null,
                'previous_confidence' => null,
                'previous_evidence' => null,
                'current_version' => null,
                'current_confidence' => 95,
                'current_evidence' => [
                    'source' => 'HTTP header',
                    'header' => 'server',
                    'value' => 'cloudflare',
                ],
            ], $currentCompletedAt);
        });

        $this->command?->info('Data simulasi Technology Changes berhasil dibuat.');
        $this->command?->info('OPD: [DEMO] SIMONTA Technology Changes');
        $this->command?->info('Total perubahan: 3');
        $this->command?->info('Added: 1 | Removed: 1 | Version Changed: 1');
    }

    private function createChange(array $data, $createdAt): void
    {
        $change = TechnologyChange::create($data);

        $change->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }
}
