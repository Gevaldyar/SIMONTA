<?php

namespace App\Services;

use App\Models\Website;
use Throwable;

class DomainSslMonitoringService
{
    public function __construct(
        private readonly DomainRdapService $domainRdapService,
        private readonly SslCertificateMonitorService $sslCertificateMonitorService,
        private readonly DomainSslNotificationService $notificationService
    ) {}

    /**
     * Memeriksa domain dan SSL satu website,
     * kemudian menyimpan hasil keduanya ke database.
     */
    public function checkWebsite(Website $website): array
    {
        // Network request dilakukan di luar database transaction.
        $domainResult = $this->inspectDomain($website);
        $sslResult = $this->inspectSsl($website);

        $domainCheck = $website->domainRegistrationChecks()
            ->create([
                'domain' => $domainResult['domain']
                    ?? $this->extractHostname($website->url),

                'status' => $domainResult['status'] ?? 'error',
                'registrar' => $domainResult['registrar'] ?? null,
                'registered_at' => $domainResult['registered_at'] ?? null,
                'expires_at' => $domainResult['expires_at'] ?? null,
                'days_remaining' => $domainResult['days_remaining'] ?? null,
                'nameservers' => $domainResult['nameservers'] ?? [],
                'rdap_server' => $domainResult['rdap_server'] ?? null,
                'checked_at' => $domainResult['checked_at'] ?? now(),
                'error_message' => $domainResult['error_message'] ?? null,
                'metadata' => $domainResult['metadata'] ?? [],
            ]);

        $sslCheck = $website->sslCertificateChecks()
            ->create([
                'hostname' => $sslResult['hostname']
                    ?? $this->extractHostname($website->url),

                'status' => $sslResult['status'] ?? 'error',
                'verification' => $sslResult['verification'] ?? 'unavailable',
                'subject' => $sslResult['subject'] ?? null,
                'issuer' => $sslResult['issuer'] ?? null,
                'valid_from' => $sslResult['valid_from'] ?? null,
                'valid_to' => $sslResult['valid_to'] ?? null,
                'days_remaining' => $sslResult['days_remaining'] ?? null,
                'subject_alt_names' => $sslResult['subject_alt_names'] ?? [],
                'fingerprint_sha256' => $sslResult['fingerprint_sha256'] ?? null,
                'checked_at' => $sslResult['checked_at'] ?? now(),
                'error_message' => $sslResult['error_message'] ?? null,
                'metadata' => $sslResult['metadata'] ?? [],
            ]);

        // Notifikasi dievaluasi setelah kedua hasil tersimpan.
        $this->notificationService->notifyForChecks(
            $domainCheck,
            $sslCheck
        );

        return [
            'website_id' => $website->id,
            'domain_check' => $domainCheck,
            'ssl_check' => $sslCheck,
        ];
    }

    /**
     * Menjalankan pemeriksaan RDAP secara independen.
     */
    private function inspectDomain(Website $website): array
    {
        try {
            return $this->domainRdapService->inspect($website->url);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'domain' => $this->extractHostname($website->url),
                'status' => 'error',
                'checked_at' => now()->toISOString(),
                'error_message' => 'Pemeriksaan registrasi domain gagal.',
                'metadata' => [],
            ];
        }
    }

    /**
     * Menjalankan pemeriksaan SSL secara independen.
     */
    private function inspectSsl(Website $website): array
    {
        try {
            return $this->sslCertificateMonitorService->inspect(
                $website->url
            );
        } catch (Throwable $exception) {
            report($exception);

            return [
                'hostname' => $this->extractHostname($website->url),
                'status' => 'error',
                'verification' => 'unavailable',
                'checked_at' => now()->toISOString(),
                'error_message' => 'Pemeriksaan sertifikat SSL gagal.',
                'metadata' => [],
            ];
        }
    }

    /**
     * Mengambil hostname dari URL sebagai fallback.
     */
    private function extractHostname(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host)
            ? strtolower($host)
            : null;
    }
}
