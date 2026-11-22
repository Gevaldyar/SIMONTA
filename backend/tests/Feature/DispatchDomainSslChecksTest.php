<?php

namespace Tests\Feature;

use App\Jobs\RunDomainSslCheckJob;
use App\Models\Opd;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchDomainSslChecksTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite(
        string $name,
        bool $websiteActive = true,
        bool $monitoringEnabled = true,
        bool $opdActive = true
    ): Website {
        $opd = Opd::create([
            'name' => "OPD {$name}",
            'is_active' => $opdActive,
        ]);

        return $opd->websites()->create([
            'name' => $name,
            'url' => 'https://example.com',
            'is_active' => $websiteActive,
            'monitoring_enabled' => $monitoringEnabled,
            'monitoring_interval_minutes' => 10,
        ]);
    }

    private function createDomainHistory(Website $website): void
    {
        $website->domainRegistrationChecks()->create([
            'domain' => 'example.com',
            'status' => 'valid',
            'days_remaining' => 365,
            'checked_at' => now(),
        ]);
    }

    private function createSslHistory(Website $website): void
    {
        $website->sslCertificateChecks()->create([
            'hostname' => 'example.com',
            'status' => 'valid',
            'verification' => 'passed',
            'days_remaining' => 180,
            'checked_at' => now(),
        ]);
    }

    public function test_command_queues_active_websites(): void
    {
        Queue::fake();

        $website = $this->createWebsite('Aktif');

        $this->createWebsite(
            'Website Nonaktif',
            websiteActive: false
        );

        $this->createWebsite(
            'Monitoring Nonaktif',
            monitoringEnabled: false
        );

        $this->createWebsite(
            'OPD Nonaktif',
            opdActive: false
        );

        $this->artisan('simonta:check-domain-ssl')
            ->assertExitCode(0)
            ->expectsOutput('Pemeriksaan Domain & SSL masuk antrean: 1')
            ->expectsOutput('Website dilewati: 0');

        Queue::assertPushed(
            RunDomainSslCheckJob::class,
            fn ($job) => $job->websiteId === $website->id
        );

        Queue::assertPushed(RunDomainSslCheckJob::class, 1);
    }

    public function test_command_skips_website_already_checked_today(): void
    {
        Queue::fake();

        $website = $this->createWebsite('Sudah Diperiksa');

        $this->createDomainHistory($website);
        $this->createSslHistory($website);

        $this->artisan('simonta:check-domain-ssl')
            ->assertExitCode(0)
            ->expectsOutput('Pemeriksaan Domain & SSL masuk antrean: 0')
            ->expectsOutput('Website dilewati: 1');

        Queue::assertNothingPushed();
    }

    public function test_command_queues_website_when_only_one_check_exists(): void
    {
        Queue::fake();

        $website = $this->createWebsite('Riwayat Tidak Lengkap');

        // Domain sudah diperiksa, tetapi SSL belum.
        $this->createDomainHistory($website);

        $this->artisan('simonta:check-domain-ssl')
            ->assertExitCode(0);

        Queue::assertPushed(
            RunDomainSslCheckJob::class,
            fn ($job) => $job->websiteId === $website->id
        );
    }

    public function test_command_can_target_one_website(): void
    {
        Queue::fake();

        $websiteA = $this->createWebsite('Website A');
        $this->createWebsite('Website B');

        $this->artisan('simonta:check-domain-ssl', [
            '--website' => $websiteA->id,
        ])->assertExitCode(0);

        Queue::assertPushed(
            RunDomainSslCheckJob::class,
            fn ($job) => $job->websiteId === $websiteA->id
        );

        Queue::assertPushed(RunDomainSslCheckJob::class, 1);
    }

    public function test_command_rejects_invalid_website_option(): void
    {
        Queue::fake();

        $this->artisan('simonta:check-domain-ssl', [
            '--website' => 'invalid',
        ])->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    public function test_command_rejects_nonexistent_website(): void
    {
        Queue::fake();

        $this->artisan('simonta:check-domain-ssl', [
            '--website' => 99999,
        ])->assertExitCode(1);

        Queue::assertNothingPushed();
    }
}
