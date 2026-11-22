<?php

namespace App\Services;

use App\Models\DomainRegistrationCheck;
use App\Models\SslCertificateCheck;
use App\Models\User;
use App\Models\Website;
use App\Notifications\SimontaEventNotification;
use Throwable;

class DomainSslNotificationService
{
    private const EXPIRY_THRESHOLDS = [30, 14, 7];

    /**
     * Memproses notifikasi dari hasil pemeriksaan domain dan SSL.
     */
    public function notifyForChecks(
        DomainRegistrationCheck $domainCheck,
        SslCertificateCheck $sslCheck
    ): void {
        try {
            $website = Website::withTrashed()
                ->with([
                    'opd' => fn ($query) => $query->withTrashed(),
                ])
                ->find($domainCheck->website_id);

            if (! $website) {
                return;
            }

            $websiteName = $website->name ?: $website->url;
            $opdName = $website->opd?->name ?? 'OPD tidak diketahui';

            $this->notifyDomainExpiry(
                $domainCheck,
                $website,
                $websiteName,
                $opdName
            );

            $this->notifySslExpiry(
                $sslCheck,
                $website,
                $websiteName,
                $opdName
            );

            $this->notifySslVerificationTransition(
                $sslCheck,
                $website,
                $websiteName,
                $opdName
            );
        } catch (Throwable $exception) {
            // Kegagalan notifikasi tidak boleh merusak hasil pemeriksaan.
            report($exception);
        }
    }

    /**
     * Notifikasi masa berlaku registrasi domain.
     */
    private function notifyDomainExpiry(
        DomainRegistrationCheck $check,
        Website $website,
        string $websiteName,
        string $opdName
    ): void {
        $expiresAt = $check->expires_at;
        $daysRemaining = $check->days_remaining;

        // Tidak membuat peringatan jika data expiration tidak tersedia.
        if (! $expiresAt || $daysRemaining === null) {
            return;
        }

        $expirationKey = $expiresAt->utc()->format('YmdHis');

        if (
            $check->status === 'expired'
            || $expiresAt->lessThanOrEqualTo(now())
        ) {
            $eventKey = "domain:website:{$website->id}:expired:{$expirationKey}";

            $this->sendOnce($eventKey, [
                'type' => 'domain_expired',
                'severity' => 'danger',
                'title' => 'Domain telah kedaluwarsa',
                'message' => "Registrasi domain {$check->domain} milik {$opdName} telah melewati tanggal kedaluwarsa.",
                'event_key' => $eventKey,
                'website_id' => $website->id,
                'website_name' => $websiteName,
                'opd_name' => $opdName,
                'asset_type' => 'domain',
                'domain' => $check->domain,
                'expires_at' => $expiresAt->toISOString(),
                'days_remaining' => $daysRemaining,
                'url' => '/domain-ssl',
            ]);

            return;
        }

        $threshold = $this->expiryThreshold($daysRemaining);

        if ($threshold === null) {
            return;
        }

        $eventKey = "domain:website:{$website->id}:expiry:{$expirationKey}:{$threshold}";

        $this->sendOnce($eventKey, [
            'type' => 'domain_expiry_warning',
            'severity' => 'warning',
            'title' => "Domain akan kedaluwarsa dalam {$daysRemaining} hari",
            'message' => "Registrasi domain {$check->domain} milik {$opdName} akan kedaluwarsa pada {$expiresAt->format('d-m-Y')}. Segera koordinasikan perpanjangan domain.",
            'event_key' => $eventKey,
            'website_id' => $website->id,
            'website_name' => $websiteName,
            'opd_name' => $opdName,
            'asset_type' => 'domain',
            'domain' => $check->domain,
            'days_remaining' => $daysRemaining,
            'expires_at' => $expiresAt->toISOString(),
            'threshold_days' => $threshold,
            'url' => '/domain-ssl',
        ]);
    }

    /**
     * Notifikasi masa berlaku sertifikat SSL.
     */
    private function notifySslExpiry(
        SslCertificateCheck $check,
        Website $website,
        string $websiteName,
        string $opdName
    ): void {
        $expiresAt = $check->valid_to;
        $daysRemaining = $check->days_remaining;

        // Sertifikat yang gagal diverifikasi tidak boleh dianggap
        // mempunyai tanggal kedaluwarsa yang sudah diketahui.
        if (
            $check->verification !== 'passed'
            || ! $expiresAt
            || $daysRemaining === null
        ) {
            return;
        }

        $expirationKey = $expiresAt->utc()->format('YmdHis');

        if (
            $check->status === 'expired'
            || $expiresAt->lessThanOrEqualTo(now())
        ) {
            $eventKey = "ssl:website:{$website->id}:expired:{$expirationKey}";

            $this->sendOnce($eventKey, [
                'type' => 'ssl_expired',
                'severity' => 'danger',
                'title' => 'Sertifikat SSL telah kedaluwarsa',
                'message' => "Sertifikat SSL untuk {$check->hostname} milik {$opdName} telah melewati masa berlaku.",
                'event_key' => $eventKey,
                'website_id' => $website->id,
                'website_name' => $websiteName,
                'opd_name' => $opdName,
                'asset_type' => 'ssl',
                'hostname' => $check->hostname,
                'issuer' => $check->issuer,
                'expires_at' => $expiresAt->toISOString(),
                'days_remaining' => $daysRemaining,
                'url' => '/domain-ssl',
            ]);

            return;
        }

        $threshold = $this->expiryThreshold($daysRemaining);

        if ($threshold === null) {
            return;
        }

        $eventKey = "ssl:website:{$website->id}:expiry:{$expirationKey}:{$threshold}";

        $this->sendOnce($eventKey, [
            'type' => 'ssl_expiry_warning',
            'severity' => 'warning',
            'title' => "Sertifikat SSL akan kedaluwarsa dalam {$daysRemaining} hari",
            'message' => "Sertifikat SSL untuk {$check->hostname} milik {$opdName} akan kedaluwarsa pada {$expiresAt->format('d-m-Y')}. Segera lakukan perpanjangan sertifikat.",
            'event_key' => $eventKey,
            'website_id' => $website->id,
            'website_name' => $websiteName,
            'opd_name' => $opdName,
            'asset_type' => 'ssl',
            'hostname' => $check->hostname,
            'issuer' => $check->issuer,
            'days_remaining' => $daysRemaining,
            'expires_at' => $expiresAt->toISOString(),
            'threshold_days' => $threshold,
            'url' => '/domain-ssl',
        ]);
    }

    /**
     * Mengirim notifikasi hanya ketika status verifikasi TLS berubah.
     *
     * Kegagalan yang sama tidak menghasilkan notifikasi setiap hari.
     * Kegagalan baru setelah pemulihan akan memiliki event key baru.
     */
    private function notifySslVerificationTransition(
        SslCertificateCheck $check,
        Website $website,
        string $websiteName,
        string $opdName
    ): void {
        $previous = SslCertificateCheck::query()
            ->where('website_id', $website->id)
            ->where('id', '<', $check->id)
            ->latest('id')
            ->first();

        $currentFailed = $this->isVerificationFailed($check);

        $previousFailed = $previous
            ? $this->isVerificationFailed($previous)
            : false;

        if ($currentFailed && ! $previousFailed) {
            $lastPassed = SslCertificateCheck::query()
                ->where('website_id', $website->id)
                ->where('id', '<', $check->id)
                ->where('verification', 'passed')
                ->latest('id')
                ->first();

            $generation = $lastPassed?->id ?? 'initial';

            $eventKey = "ssl:website:{$website->id}:verification-failed:after:{$generation}";

            $this->sendOnce($eventKey, [
                'type' => 'ssl_verification_failed',
                'severity' => 'danger',
                'title' => 'Verifikasi sertifikat TLS gagal',
                'message' => "SIMONTA tidak dapat memverifikasi sertifikat TLS pada {$check->hostname} milik {$opdName}. Periksa masa berlaku, hostname, dan rantai sertifikat.",
                'event_key' => $eventKey,
                'website_id' => $website->id,
                'website_name' => $websiteName,
                'opd_name' => $opdName,
                'asset_type' => 'ssl',
                'hostname' => $check->hostname,
                'verification' => $check->verification,
                'error_message' => $check->error_message,
                'url' => '/domain-ssl',
            ]);

            return;
        }

        if (
            ! $currentFailed
            && $previousFailed
            && $check->verification === 'passed'
        ) {
            $eventKey = "ssl:website:{$website->id}:verification-restored:after:{$previous->id}";

            $this->sendOnce($eventKey, [
                'type' => 'ssl_verification_restored',
                'severity' => 'info',
                'title' => 'Verifikasi sertifikat TLS kembali normal',
                'message' => "Sertifikat TLS pada {$check->hostname} milik {$opdName} berhasil diverifikasi kembali.",
                'event_key' => $eventKey,
                'website_id' => $website->id,
                'website_name' => $websiteName,
                'opd_name' => $opdName,
                'asset_type' => 'ssl',
                'hostname' => $check->hostname,
                'verification' => 'passed',
                'url' => '/domain-ssl',
            ]);
        }
    }

    /**
     * Menentukan ambang peringatan berdasarkan sisa hari.
     */
    private function expiryThreshold(int $daysRemaining): ?int
    {
        foreach (self::EXPIRY_THRESHOLDS as $threshold) {
            if ($daysRemaining <= $threshold) {
                return $threshold;
            }
        }

        return null;
    }

    /**
     * Menentukan apakah hasil pemeriksaan menunjukkan
     * kegagalan verifikasi TLS.
     */
    private function isVerificationFailed(
        SslCertificateCheck $check
    ): bool {
        return $check->status === 'verification_failed'
            || $check->verification === 'failed';
    }

    /**
     * Mengirim notifikasi satu kali per event kepada setiap pengguna.
     */
    private function sendOnce(
        string $eventKey,
        array $payload
    ): void {
        User::query()
            ->select('id')
            ->chunkById(100, function ($users) use (
                $eventKey,
                $payload
            ) {
                foreach ($users as $user) {
                    try {
                        $alreadySent = $user->notifications()
                            ->where('data->event_key', $eventKey)
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        $user->notify(
                            new SimontaEventNotification($payload)
                        );
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }
            });
    }
}
