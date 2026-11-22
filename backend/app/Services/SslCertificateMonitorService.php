<?php

namespace App\Services;

use Carbon\Carbon;
use RuntimeException;
use Throwable;

class SslCertificateMonitorService
{
    private const EXPIRING_SOON_DAYS = 30;

    private const CONNECT_TIMEOUT_SECONDS = 8;

    public function __construct(
        private readonly PublicUrlValidator $urlValidator
    ) {}

    /**
     * Memeriksa sertifikat TLS tanpa mengunduh konten website.
     *
     * Verifikasi sertifikat dan hostname tetap diwajibkan.
     * Koneksi diarahkan ke IP publik hasil validasi DNS.
     */
    public function inspect(string $websiteUrl): array
    {
        $checkedAt = now();

        $parts = parse_url(trim($websiteUrl));

        if (
            ! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
        ) {
            return $this->result(
                hostname: is_array($parts)
                    ? ($parts['host'] ?? null)
                    : null,
                status: 'unsupported',
                verification: 'unavailable',
                checkedAt: $checkedAt,
                errorMessage: 'Pemeriksaan sertifikat TLS memerlukan URL HTTPS yang valid.'
            );
        }

        try {
            // Gunakan validator SSRF yang sama dengan HttpInspector.
            $validated = $this->urlValidator->validate($websiteUrl);

            $hostname = strtolower($validated['host']);

            $port = (int) $validated['port'];

            $addresses = $validated['addresses'] ?? [];

            if (! is_array($addresses) || $addresses === []) {
                return $this->result(
                    hostname: $hostname,
                    status: 'unavailable',
                    verification: 'unavailable',
                    checkedAt: $checkedAt,
                    errorMessage: 'Tidak ditemukan alamat IP publik yang dapat digunakan.'
                );
            }

            // Gunakan alamat pertama yang sudah lolos validasi DNS.
            $ip = $addresses[0];

            $certificate = $this->connectAndReadCertificate(
                $hostname,
                $port,
                $ip
            );

            return $this->summarizeCertificate(
                $hostname,
                $certificate,
                $checkedAt
            );
        } catch (Throwable $exception) {
            report($exception);

            $verificationFailed = $this->isVerificationFailure(
                $exception->getMessage()
            );

            return $this->result(
                hostname: $parts['host'],
                status: $verificationFailed
                    ? 'verification_failed'
                    : 'unavailable',
                verification: $verificationFailed
                    ? 'failed'
                    : 'unavailable',
                checkedAt: $checkedAt,
                errorMessage: substr(
                    $exception->getMessage(),
                    0,
                    1000
                )
            );
        }
    }

    /**
     * Membuka TLS connection ke IP yang telah divalidasi.
     *
     * Tidak mengirim HTTP request dan tidak mengikuti redirect.
     * Verifikasi sertifikat dan hostname tidak boleh dimatikan.
     */
    protected function connectAndReadCertificate(
        string $hostname,
        int $port,
        string $ip
    ): array {
        $isIpAddress = filter_var(
            $hostname,
            FILTER_VALIDATE_IP
        ) !== false;

        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,

            // Verifikasi hostname asli, bukan IP hasil DNS.
            'peer_name' => $hostname,

            // Mengambil sertifikat peer setelah handshake berhasil.
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,

            'disable_compression' => true,
        ];

        // SNI digunakan untuk hostname DNS, bukan alamat IP.
        if (! $isIpAddress) {
            $sslOptions['SNI_enabled'] = true;
            $sslOptions['SNI_server_name'] = $hostname;
        } else {
            $sslOptions['SNI_enabled'] = false;
        }

        $context = stream_context_create([
            'ssl' => $sslOptions,
        ]);

        // IPv6 perlu tanda kurung pada alamat tujuan.
        $remoteAddress = str_contains($ip, ':')
            ? '[' . $ip . ']'
            : $ip;
$errno = 0;
$errorMessage = '';
$connectionWarnings = [];

// Tangkap warning OpenSSL agar penyebab kegagalan
// TLS handshake tidak hilang.
set_error_handler(
    function (int $severity, string $message) use (&$connectionWarnings): bool {
        if (($severity & E_WARNING) === E_WARNING) {
            $connectionWarnings[] = $message;

            return true;
        }

        return false;
    },
    E_WARNING
);

try {
    $socket = stream_socket_client(
        "tls://{$remoteAddress}:{$port}",
        $errno,
        $errorMessage,
        self::CONNECT_TIMEOUT_SECONDS,
        STREAM_CLIENT_CONNECT,
        $context
    );
} finally {
    // Kembalikan error handler Laravel/PHP seperti semula.
    restore_error_handler();
}

if (! is_resource($socket)) {
    $details = implode(' | ', array_unique($connectionWarnings));

    if ($errorMessage !== '') {
        $details .= ($details !== '' ? ' | ' : '') . $errorMessage;
    }

    // Hilangkan prefix berulang agar pesan lebih mudah dibaca.
    $details = preg_replace(
        '/stream_socket_client\(\):\s*/',
        '',
        $details
    ) ?? $details;

    $details = trim(
        preg_replace('/\s+/', ' ', $details) ?? $details
    );

    throw new RuntimeException(
        $details !== ''
            ? $details
            : "TLS connection failed with error code {$errno}."
    );
}


        try {
            $parameters = stream_context_get_params($socket);

            $certificate = $parameters['options']['ssl']['peer_certificate']
                ?? null;

            if (! $certificate instanceof \OpenSSLCertificate) {
                throw new RuntimeException(
                    'Peer certificate tidak tersedia setelah TLS handshake.'
                );
            }

            $parsed = openssl_x509_parse($certificate);

            if (! is_array($parsed)) {
                throw new RuntimeException(
                    'Sertifikat TLS berhasil diterima tetapi gagal diparsing.'
                );
            }

            $fingerprint = openssl_x509_fingerprint(
                $certificate,
                'sha256'
            );

            $streamMetadata = stream_get_meta_data($socket);

            $crypto = $streamMetadata['crypto'] ?? [];

            return [
                'subject' => $this->formatDistinguishedName(
                    $parsed['subject'] ?? []
                ),

                'issuer' => $this->formatDistinguishedName(
                    $parsed['issuer'] ?? []
                ),

                'valid_from_ts' => isset($parsed['validFrom_time_t'])
                    ? (int) $parsed['validFrom_time_t']
                    : null,

                'valid_to_ts' => isset($parsed['validTo_time_t'])
                    ? (int) $parsed['validTo_time_t']
                    : null,

                'subject_alt_names' => $this->parseSubjectAltNames(
                    $parsed['extensions']['subjectAltName'] ?? ''
                ),

                'fingerprint_sha256' => is_string($fingerprint)
                    ? strtoupper(str_replace(':', '', $fingerprint))
                    : null,

                'protocol' => $crypto['protocol'] ?? null,

                'cipher_name' => $crypto['cipher_name'] ?? null,
            ];
        } finally {
            fclose($socket);
        }
    }

    /**
     * Mengubah informasi sertifikat menjadi format database SIMONTA.
     */
    protected function summarizeCertificate(
        string $hostname,
        array $certificate,
        Carbon $checkedAt
    ): array {
        $validFromTimestamp = $certificate['valid_from_ts'] ?? null;
        $validToTimestamp = $certificate['valid_to_ts'] ?? null;

        $validFromTimestamp = is_numeric($validFromTimestamp)
            ? (int) $validFromTimestamp
            : null;

        $validToTimestamp = is_numeric($validToTimestamp)
            ? (int) $validToTimestamp
            : null;

        $daysRemaining = $validToTimestamp !== null
            ? (int) ceil(
                ($validToTimestamp - $checkedAt->timestamp) / 86400
            )
            : null;

        if (
            $validFromTimestamp !== null
            && $checkedAt->timestamp < $validFromTimestamp
        ) {
            $status = 'not_yet_valid';
        } elseif (
            $validToTimestamp !== null
            && $checkedAt->timestamp >= $validToTimestamp
        ) {
            $status = 'expired';
        } elseif ($validToTimestamp === null) {
            $status = 'unavailable';
        } elseif ($daysRemaining <= self::EXPIRING_SOON_DAYS) {
            $status = 'expiring_soon';
        } else {
            $status = 'valid';
        }

        return [
            'hostname' => $hostname,
            'status' => $status,
            'verification' => 'passed',

            'subject' => $certificate['subject'] ?? null,
            'issuer' => $certificate['issuer'] ?? null,

            'valid_from' => $validFromTimestamp !== null
                ? Carbon::createFromTimestampUTC(
                    $validFromTimestamp
                )->toISOString()
                : null,

            'valid_to' => $validToTimestamp !== null
                ? Carbon::createFromTimestampUTC(
                    $validToTimestamp
                )->toISOString()
                : null,

            'days_remaining' => $daysRemaining,

            'subject_alt_names' => $certificate['subject_alt_names'] ?? [],

            'fingerprint_sha256' => $certificate['fingerprint_sha256'] ?? null,

            'checked_at' => $checkedAt->toISOString(),

            'error_message' => null,

            'metadata' => [
                'protocol' => $certificate['protocol'] ?? null,
                'cipher_name' => $certificate['cipher_name'] ?? null,
            ],
        ];
    }

    /**
     * Mengubah Distinguished Name menjadi teks.
     */
    private function formatDistinguishedName(array $name): ?string
    {
        $parts = [];

        foreach ($name as $key => $value) {
            foreach ((array) $value as $item) {
                if (! is_scalar($item) || trim((string) $item) === '') {
                    continue;
                }

                $parts[] = $key . '=' . trim((string) $item);
            }
        }

        return $parts !== []
            ? implode(', ', $parts)
            : null;
    }

    /**
     * Memecah Subject Alternative Names menjadi array.
     */
    private function parseSubjectAltNames(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return collect(preg_split('/\s*,\s*/', $value))
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn ($item) => trim($item))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Membedakan kegagalan verifikasi TLS dari koneksi tidak tersedia.
     */
    private function isVerificationFailure(string $message): bool
{
    $message = strtolower($message);

    $indicators = [
        'certificate verify failed',
        'certificate verification failed',
        'certificate has expired',
        'certificate is not yet valid',
        'hostname mismatch',
        'does not match',
        'self-signed certificate',
        'self signed certificate',
        'unable to get local issuer',
        'unable to verify the first certificate',
        'peer certificate',
    ];

    foreach ($indicators as $indicator) {
        if (str_contains($message, $indicator)) {
            return true;
        }
    }

    return false;
}

    /**
     * Struktur hasil ketika sertifikat tidak dapat diperiksa.
     */
    private function result(
        ?string $hostname,
        string $status,
        string $verification,
        Carbon $checkedAt,
        ?string $errorMessage = null
    ): array {
        return [
            'hostname' => $hostname,
            'status' => $status,
            'verification' => $verification,
            'subject' => null,
            'issuer' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_remaining' => null,
            'subject_alt_names' => [],
            'fingerprint_sha256' => null,
            'checked_at' => $checkedAt->toISOString(),
            'error_message' => $errorMessage,
            'metadata' => [],
        ];
    }
}
