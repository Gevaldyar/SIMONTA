<?php

namespace App\Console\Commands;

use App\Jobs\RunDomainSslCheckJob;
use App\Models\DomainRegistrationCheck;
use App\Models\SslCertificateCheck;
use App\Models\Website;
use Illuminate\Console\Command;

class DispatchDomainSslChecks extends Command
{
    protected $signature = 'simonta:check-domain-ssl
        {--website= : ID website tertentu yang ingin diperiksa}';

    protected $description =
        'Memasukkan pemeriksaan registrasi domain dan sertifikat SSL ke antrean SIMONTA.';

    public function handle(): int
    {
        $websiteOption = $this->option('website');

        if (
            $websiteOption !== null
            && (
                ! ctype_digit((string) $websiteOption)
                || (int) $websiteOption < 1
            )
        ) {
            $this->error('ID website harus berupa angka positif.');

            return self::FAILURE;
        }

        if (
            $websiteOption !== null
            && ! Website::query()->whereKey((int) $websiteOption)->exists()
        ) {
            $this->error('Website dengan ID tersebut tidak ditemukan.');

            return self::FAILURE;
        }

        $queued = 0;
        $skipped = 0;

        $query = Website::query()
            ->select('id')
            ->where('is_active', true)
            ->where('monitoring_enabled', true)
            ->whereHas('opd', function ($opdQuery) {
                $opdQuery->where('is_active', true);
            });

        if ($websiteOption !== null) {
            $query->whereKey((int) $websiteOption);
        }

        $query->orderBy('id')
            ->chunkById(100, function ($websites) use (
                &$queued,
                &$skipped
            ) {
                foreach ($websites as $candidate) {
                    if ($this->hasCompletedToday($candidate->id)) {
                        $skipped++;
                        continue;
                    }

                    RunDomainSslCheckJob::dispatch($candidate->id)
                        ->afterCommit();

                    $queued++;
                }
            });

        $this->info("Pemeriksaan Domain & SSL masuk antrean: {$queued}");
        $this->info("Website dilewati: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Periksa apakah kedua jenis pemeriksaan sudah tersedia hari ini.
     */
    private function hasCompletedToday(int $websiteId): bool
    {
        $start = now()->startOfDay();
        $end = $start->copy()->addDay();

        $domainDone = DomainRegistrationCheck::query()
            ->where('website_id', $websiteId)
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->exists();

        $sslDone = SslCertificateCheck::query()
            ->where('website_id', $websiteId)
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->exists();

        return $domainDone && $sslDone;
    }
}
