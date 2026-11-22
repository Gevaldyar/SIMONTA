<?php

namespace Tests\Feature;

use App\Models\DomainRegistrationCheck;
use App\Models\Opd;
use App\Models\SslCertificateCheck;
use App\Models\User;
use App\Models\Website;
use App\Services\DomainRdapService;
use App\Services\DomainSslMonitoringService;
use App\Services\SslCertificateMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use App\Services\DomainSslNotificationService;

class DomainSslMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite(): Website
    {
        $opd = Opd::create([
            'name' => 'OPD Testing',
            'is_active' => true,
        ]);

        return $opd->websites()->create([
            'name' => 'Portal Testing',
            'url' => 'https://example.id',
            'is_active' => true,
            'monitoring_enabled' => true,
            'monitoring_interval_minutes' => 10,
        ]);
    }

    private function domainResult(): array
    {
        return [
            'domain' => 'example.id',
            'status' => 'valid',
            'registrar' => 'Example Registrar',
            'registered_at' => now()->subYear()->toISOString(),
            'expires_at' => now()->addYear()->toISOString(),
            'days_remaining' => 365,
            'nameservers' => [
                'ns1.example.id',
                'ns2.example.id',
            ],
            'rdap_server' => 'https://rdap.example/rdap',
            'checked_at' => now()->toISOString(),
            'error_message' => null,
            'metadata' => [
                'rdap_handle' => 'EXAMPLE-ID',
            ],
        ];
    }

    private function sslResult(): array
    {
        return [
            'hostname' => 'example.id',
            'status' => 'valid',
            'verification' => 'passed',
            'subject' => 'CN=example.id',
            'issuer' => 'CN=Example CA',
            'valid_from' => now()->subMonth()->toISOString(),
            'valid_to' => now()->addMonths(6)->toISOString(),
            'days_remaining' => 180,
            'subject_alt_names' => [
                'DNS:example.id',
                'DNS:www.example.id',
            ],
            'fingerprint_sha256' => str_repeat('A', 64),
            'checked_at' => now()->toISOString(),
            'error_message' => null,
            'metadata' => [
                'protocol' => 'TLSv1.3',
            ],
        ];
    }

    public function test_it_saves_domain_and_ssl_check_results(): void
    {
        $website = $this->createWebsite();

        $domainService = Mockery::mock(DomainRdapService::class);

        $domainService->shouldReceive('inspect')
            ->once()
            ->with('https://example.id')
            ->andReturn($this->domainResult());

        $sslService = Mockery::mock(SslCertificateMonitorService::class);

        $sslService->shouldReceive('inspect')
            ->once()
            ->with('https://example.id')
            ->andReturn($this->sslResult());

        $service = new DomainSslMonitoringService(
    $domainService,
    $sslService,
    app(DomainSslNotificationService::class)
);

        $result = $service->checkWebsite($website);

        $this->assertSame($website->id, $result['website_id']);

        $this->assertInstanceOf(
            DomainRegistrationCheck::class,
            $result['domain_check']
        );

        $this->assertInstanceOf(
            SslCertificateCheck::class,
            $result['ssl_check']
        );

        $this->assertSame(
            'valid',
            $result['domain_check']->status
        );

        $this->assertSame(
            'valid',
            $result['ssl_check']->status
        );

        $this->assertDatabaseHas('domain_registration_checks', [
            'website_id' => $website->id,
            'domain' => 'example.id',
            'status' => 'valid',
            'registrar' => 'Example Registrar',
            'days_remaining' => 365,
        ]);

        $this->assertDatabaseHas('ssl_certificate_checks', [
            'website_id' => $website->id,
            'hostname' => 'example.id',
            'status' => 'valid',
            'verification' => 'passed',
            'days_remaining' => 180,
        ]);
    }

    public function test_domain_result_is_saved_when_ssl_check_throws(): void
    {
        $website = $this->createWebsite();

        $domainService = Mockery::mock(DomainRdapService::class);

        $domainService->shouldReceive('inspect')
            ->once()
            ->andReturn($this->domainResult());

        $sslService = Mockery::mock(SslCertificateMonitorService::class);

        $sslService->shouldReceive('inspect')
            ->once()
            ->andThrow(new RuntimeException('SSL test failure'));

        $service = new DomainSslMonitoringService(
    $domainService,
    $sslService,
    app(DomainSslNotificationService::class)
);

        $result = $service->checkWebsite($website);

        $this->assertSame(
            'valid',
            $result['domain_check']->status
        );

        $this->assertSame(
            'error',
            $result['ssl_check']->status
        );

        $this->assertDatabaseHas('domain_registration_checks', [
            'website_id' => $website->id,
            'status' => 'valid',
        ]);

        $this->assertDatabaseHas('ssl_certificate_checks', [
            'website_id' => $website->id,
            'status' => 'error',
            'verification' => 'unavailable',
        ]);
    }

    public function test_ssl_result_is_saved_when_domain_check_throws(): void
    {
        $website = $this->createWebsite();

        $domainService = Mockery::mock(DomainRdapService::class);

        $domainService->shouldReceive('inspect')
            ->once()
            ->andThrow(new RuntimeException('RDAP test failure'));

        $sslService = Mockery::mock(SslCertificateMonitorService::class);

        $sslService->shouldReceive('inspect')
            ->once()
            ->andReturn($this->sslResult());

        $service = new DomainSslMonitoringService(
    $domainService,
    $sslService,
    app(DomainSslNotificationService::class)
);

        $result = $service->checkWebsite($website);

        $this->assertSame(
            'error',
            $result['domain_check']->status
        );

        $this->assertSame(
            'valid',
            $result['ssl_check']->status
        );

        $this->assertDatabaseHas('domain_registration_checks', [
            'website_id' => $website->id,
            'status' => 'error',
        ]);

        $this->assertDatabaseHas('ssl_certificate_checks', [
            'website_id' => $website->id,
            'status' => 'valid',
            'verification' => 'passed',
        ]);
    }

    public function test_it_keeps_each_check_as_separate_history_record(): void
    {
        $website = $this->createWebsite();

        $domainService = Mockery::mock(DomainRdapService::class);

        $domainService->shouldReceive('inspect')
            ->twice()
            ->andReturn($this->domainResult());

        $sslService = Mockery::mock(SslCertificateMonitorService::class);

        $sslService->shouldReceive('inspect')
            ->twice()
            ->andReturn($this->sslResult());

        $service = new DomainSslMonitoringService(
    $domainService,
    $sslService,
    app(DomainSslNotificationService::class)
);

        $service->checkWebsite($website);
        $service->checkWebsite($website);

        $this->assertSame(
            2,
            DomainRegistrationCheck::where(
                'website_id',
                $website->id
            )->count()
        );

        $this->assertSame(
            2,
            SslCertificateCheck::where(
                'website_id',
                $website->id
            )->count()
        );

        $this->assertCount(
            2,
            $website->fresh()->domainRegistrationChecks
        );

        $this->assertCount(
            2,
            $website->fresh()->sslCertificateChecks
        );
    }
}
