<?php

namespace App\Jobs;

use App\Models\DomainRegistrationCheck;
use App\Models\SslCertificateCheck;
use App\Models\Website;
use App\Services\DomainSslMonitoringService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Throwable;

#[Timeout(45)]
#[FailOnTimeout]
class RunDomainSslCheckJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    // Mencegah job ganda untuk website yang sama.
    public int $uniqueFor = 3600;

    public function __construct(
        public int $websiteId
    ) {}

    public function uniqueId(): string
    {
        return 'domain-ssl:website:' . $this->websiteId;
    }

    /**
     * Memeriksa registrasi domain dan sertifikat SSL.
     */
    public function handle(
        DomainSslMonitoringService $monitoringService
    ): void {
        $website = Website::query()
            ->whereKey($this->websiteId)
            ->where('is_active', true)
            ->where('monitoring_enabled', true)
            ->whereHas('opd', function ($query) {
                $query->where('is_active', true);
            })
            ->first();

        // Website mungkin sudah dinonaktifkan setelah job masuk antrean.
        if (! $website) {
            return;
        }

        if ($this->hasCompletedToday($website)) {
            return;
        }

        $monitoringService->checkWebsite($website);
    }

    /**
     * Kedua jenis pemeriksaan tidak diulang jika sudah selesai hari ini.
     */
    private function hasCompletedToday(Website $website): bool
    {
        $start = now()->startOfDay();
        $end = $start->copy()->addDay();

        $domainDone = DomainRegistrationCheck::query()
            ->where('website_id', $website->id)
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->exists();

        $sslDone = SslCertificateCheck::query()
            ->where('website_id', $website->id)
            ->where('checked_at', '>=', $start)
            ->where('checked_at', '<', $end)
            ->exists();

        return $domainDone && $sslDone;
    }

    /**
     * Menangani kegagalan job pada Queue Worker.
     */
    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
