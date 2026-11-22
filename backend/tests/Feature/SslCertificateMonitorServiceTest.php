<?php

namespace Tests\Feature;

use App\Services\PublicUrlValidator;
use App\Services\SslCertificateMonitorService;
use Carbon\Carbon;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SslCertificateMonitorServiceTest extends TestCase
{
    private function makeValidator(): PublicUrlValidator
    {
        return Mockery::mock(PublicUrlValidator::class);
    }

    private function validatedUrl(): array
    {
        return [
            'url' => 'https://example.com',
            'host' => 'example.com',
            'port' => 443,
            'addresses' => ['93.184.216.34'],
        ];
    }

    private function certificateData(
        int $daysRemaining = 60,
        ?int $validFromTimestamp = null
    ): array {
        $now = now();

        return [
            'subject' => 'CN=example.com, O=Example Organization',
            'issuer' => 'CN=Example Certificate Authority',
            'valid_from_ts' => $validFromTimestamp
                ?? $now->subDays(30)->timestamp,
            'valid_to_ts' => $now
                ->addDays(30)
                ->addDays($daysRemaining)
                ->timestamp,
            'subject_alt_names' => [
                'DNS:example.com',
                'DNS:www.example.com',
            ],
            'fingerprint_sha256' => str_repeat('A', 64),
            'protocol' => 'TLSv1.3',
            'cipher_name' => 'TLS_AES_256_GCM_SHA384',
        ];
    }

    public function test_valid_certificate_is_detected(): void
    {
        $validator = $this->makeValidator();

        $validator->shouldReceive('validate')
            ->once()
            ->with('https://example.com')
            ->andReturn($this->validatedUrl());

        $service = new FakeSslCertificateMonitorService(
            $validator,
            $this->certificateData(60)
        );

        $result = $service->inspect('https://example.com');

        $this->assertSame('example.com', $result['hostname']);
        $this->assertSame('valid', $result['status']);
        $this->assertSame('passed', $result['verification']);

        $this->assertSame(
            'CN=Example Certificate Authority',
            $result['issuer']
        );

        $this->assertSame(60, $result['days_remaining']);

        $this->assertCount(
            2,
            $result['subject_alt_names']
        );

        $this->assertSame(
            str_repeat('A', 64),
            $result['fingerprint_sha256']
        );

        $this->assertSame(
            'TLSv1.3',
            $result['metadata']['protocol']
        );
    }

    public function test_certificate_expiring_within_30_days_is_detected(): void
    {
        $validator = $this->makeValidator();

        $validator->shouldReceive('validate')
            ->once()
            ->andReturn($this->validatedUrl());

        $service = new FakeSslCertificateMonitorService(
            $validator,
            $this->certificateData(10)
        );

        $result = $service->inspect('https://example.com');

        $this->assertSame('expiring_soon', $result['status']);
        $this->assertSame('passed', $result['verification']);
        $this->assertSame(10, $result['days_remaining']);
    }

    public function test_expired_certificate_date_is_classified(): void
    {
        $validator = $this->makeValidator();

        $validator->shouldReceive('validate')
            ->once()
            ->andReturn($this->validatedUrl());

        $data = $this->certificateData(0);

        $data['valid_to_ts'] = now()->subDay()->timestamp;

        $service = new FakeSslCertificateMonitorService(
            $validator,
            $data
        );

        $result = $service->inspect('https://example.com');

        $this->assertSame('expired', $result['status']);
        $this->assertSame(-1, $result['days_remaining']);
    }

    public function test_not_yet_valid_certificate_is_detected(): void
    {
        $validator = $this->makeValidator();

        $validator->shouldReceive('validate')
            ->once()
            ->andReturn($this->validatedUrl());

        $data = $this->certificateData(60);

        $data['valid_from_ts'] = now()
            ->addDays(2)
            ->timestamp;

        $service = new FakeSslCertificateMonitorService(
            $validator,
            $data
        );

        $result = $service->inspect('https://example.com');

        $this->assertSame('not_yet_valid', $result['status']);
        $this->assertSame('passed', $result['verification']);
    }

    public function test_http_url_is_not_supported(): void
    {
        $validator = $this->makeValidator();

        $validator->shouldNotReceive('validate');

        $service = new FakeSslCertificateMonitorService(
            $validator,
            []
        );

        $result = $service->inspect('http://example.com');

        $this->assertSame('unsupported', $result['status']);
        $this->assertSame('unavailable', $result['verification']);
        $this->assertNull($result['valid_to']);
    }

    public function test_tls_verification_failure_is_reported(): void
    {
        $validator = $this->makeValidator();

        $validator->shouldReceive('validate')
            ->once()
            ->andReturn($this->validatedUrl());

        $service = new FakeSslCertificateMonitorService(
            $validator,
            [],
            new RuntimeException(
                'certificate verify failed: certificate has expired'
            )
        );

        $result = $service->inspect('https://example.com');

        $this->assertSame(
            'verification_failed',
            $result['status']
        );

        $this->assertSame('failed', $result['verification']);
        $this->assertNull($result['valid_to']);
    }


public function test_openssl_certificate_verify_warning_is_reported_as_verification_failure(): void
{
    $validator = $this->makeValidator();

    $validator->shouldReceive('validate')
        ->once()
        ->andReturn($this->validatedUrl());

    // Simulasi pesan warning OpenSSL yang sebenarnya
    // ketika stream_socket_client gagal melakukan TLS handshake.
    $warningMessage = implode(' | ', [
        'SSL operation failed with code 1. OpenSSL Error messages: error:0A000086:SSL routines::certificate verify failed',
        'Failed to enable crypto',
        'Unable to connect to tls://93.184.216.34:443 (Unknown error)',
    ]);

    $service = new FakeSslCertificateMonitorService(
        $validator,
        [],
        new RuntimeException($warningMessage)
    );

    $result = $service->inspect('https://example.com');

    $this->assertSame(
        'verification_failed',
        $result['status']
    );

    $this->assertSame(
        'failed',
        $result['verification']
    );

    $this->assertStringContainsString(
        'certificate verify failed',
        $result['error_message']
    );

    $this->assertNull($result['valid_to']);
}


    public function test_connection_timeout_is_unavailable_not_verification_failure(): void
    {
        $validator = $this->makeValidator();

        $validator->shouldReceive('validate')
            ->once()
            ->andReturn($this->validatedUrl());

        $service = new FakeSslCertificateMonitorService(
            $validator,
            [],
            new RuntimeException('Connection timed out')
        );

        $result = $service->inspect('https://example.com');

        $this->assertSame('unavailable', $result['status']);
        $this->assertSame('unavailable', $result['verification']);
    }
}

/**
 * Pengganti koneksi TLS agar pengujian tidak membutuhkan internet.
 */
class FakeSslCertificateMonitorService extends SslCertificateMonitorService
{
    public function __construct(
        PublicUrlValidator $urlValidator,
        private readonly array $fakeCertificate,
        private readonly ?RuntimeException $fakeException = null
    ) {
        parent::__construct($urlValidator);
    }

    protected function connectAndReadCertificate(
        string $hostname,
        int $port,
        string $ip
    ): array {
        if ($this->fakeException !== null) {
            throw $this->fakeException;
        }

        return $this->fakeCertificate;
    }
}
