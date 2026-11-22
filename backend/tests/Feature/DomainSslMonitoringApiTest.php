<?php

namespace Tests\Feature;

use App\Models\DomainRegistrationCheck;
use App\Models\Opd;
use App\Models\SslCertificateCheck;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DomainSslMonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite(string $name): Website
    {
        $opd = Opd::create([
            'name' => "OPD {$name}",
            'is_active' => true,
        ]);

        return $opd->websites()->create([
            'name' => $name,
            'url' => "https://{$name}.example.com",
            'is_active' => true,
            'monitoring_enabled' => true,
            'monitoring_interval_minutes' => 60,
        ]);
    }

    private function createDomainCheck(
        Website $website,
        string $status,
        Carbon $checkedAt,
        ?int $daysRemaining = 200
    ): DomainRegistrationCheck {
        return $website->domainRegistrationChecks()->create([
            'domain' => 'example.com',
            'status' => $status,
            'registrar' => 'Example Registrar',
            'registered_at' => $checkedAt->copy()->subYears(5),
            'expires_at' => $checkedAt->copy()->addDays(
                $daysRemaining ?? 200
            ),
            'days_remaining' => $daysRemaining,
            'nameservers' => [
                'ns1.example.com',
                'ns2.example.com',
            ],
            'rdap_server' => 'https://rdap.example.com',
            'checked_at' => $checkedAt,
        ]);
    }

    private function createSslCheck(
        Website $website,
        string $status,
        Carbon $checkedAt,
        string $verification = 'passed',
        ?int $daysRemaining = 200
    ): SslCertificateCheck {
        return $website->sslCertificateChecks()->create([
            'hostname' => 'www.example.com',
            'status' => $status,
            'verification' => $verification,
            'subject' => 'CN=www.example.com',
            'issuer' => 'Example Certificate Authority',
            'valid_from' => $checkedAt->copy()->subDays(10),
            'valid_to' => $checkedAt->copy()->addDays(
                $daysRemaining ?? 200
            ),
            'days_remaining' => $daysRemaining,
            'subject_alt_names' => [
                'DNS:www.example.com',
            ],
            'fingerprint_sha256' => str_repeat('A', 64),
            'checked_at' => $checkedAt,
        ]);
    }

    public function test_guest_cannot_view_domain_ssl_monitoring(): void
    {
        $this->getJson('/api/domain-ssl-checks')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_view_domain_ssl_history(): void
    {
        $website = $this->createWebsite('portal');

        $this->getJson(
            "/api/websites/{$website->id}/domain-ssl-history"
        )->assertUnauthorized();
    }

    public function test_admin_can_view_latest_domain_and_ssl_check_per_website(): void
    {
        $user = User::factory()->create();

        $website = $this->createWebsite('portal');

        // Hasil terbaru.
        $latestTime = Carbon::parse('2026-11-21 10:00:00');

        $latestDomain = $this->createDomainCheck(
            $website,
            'valid',
            $latestTime
        );

        $latestSsl = $this->createSslCheck(
            $website,
            'valid',
            $latestTime
        );

        // Hasil lebih lama dengan status berbeda.
        $this->createDomainCheck(
            $website,
            'expired',
            Carbon::parse('2026-11-20 10:00:00'),
            -10
        );

        $this->createSslCheck(
            $website,
            'expired',
            Carbon::parse('2026-11-20 10:00:00'),
            'passed',
            -10
        );

        $this->actingAs($user)
            ->getJson('/api/domain-ssl-checks')
            ->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('summary.domain.valid', 1)
            ->assertJsonPath('summary.domain.expired', 0)
            ->assertJsonPath('summary.ssl.valid', 1)
            ->assertJsonPath('summary.ssl.expired', 0)
            ->assertJsonPath('data.0.website.id', $website->id)
            ->assertJsonPath('data.0.domain_check.id', $latestDomain->id)
            ->assertJsonPath('data.0.domain_check.status', 'valid')
            ->assertJsonPath('data.0.ssl_check.id', $latestSsl->id)
            ->assertJsonPath('data.0.ssl_check.status', 'valid');
    }

    public function test_admin_can_filter_domain_ssl_by_status_and_opd(): void
    {
        $user = User::factory()->create();

        $expiredWebsite = $this->createWebsite('expired-portal');
        $validWebsite = $this->createWebsite('valid-portal');

        $expiredTime = Carbon::parse('2026-11-21 10:00:00');

        $this->createDomainCheck(
            $expiredWebsite,
            'expired',
            $expiredTime,
            -10
        );

        $this->createSslCheck(
            $expiredWebsite,
            'verification_failed',
            $expiredTime,
            'failed',
            null
        );

        $this->createDomainCheck(
            $validWebsite,
            'valid',
            $expiredTime,
            200
        );

        $this->createSslCheck(
            $validWebsite,
            'valid',
            $expiredTime,
            'passed',
            200
        );

        $response = $this->actingAs($user)
            ->getJson('/api/domain-ssl-checks?' . http_build_query([
                'domain_status' => 'expired',
                'opd_id' => $expiredWebsite->opd_id,
            ]));

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('data.0.website.id', $expiredWebsite->id)
            ->assertJsonPath('data.0.domain_check.status', 'expired');

        $this->actingAs($user)
            ->getJson('/api/domain-ssl-checks?ssl_status=verification_failed')
            ->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('data.0.website.id', $expiredWebsite->id)
            ->assertJsonPath(
                'data.0.ssl_check.status',
                'verification_failed'
            );
    }

    public function test_admin_can_view_separate_domain_and_ssl_history(): void
    {
        $user = User::factory()->create();

        $website = $this->createWebsite('history-portal');

        $this->createDomainCheck(
            $website,
            'expired',
            Carbon::parse('2026-11-20 10:00:00'),
            -10
        );

        $latestDomain = $this->createDomainCheck(
            $website,
            'valid',
            Carbon::parse('2026-11-21 10:00:00'),
            200
        );

        $this->createSslCheck(
            $website,
            'expired',
            Carbon::parse('2026-11-20 10:00:00'),
            'passed',
            -10
        );

        $latestSsl = $this->createSslCheck(
            $website,
            'valid',
            Carbon::parse('2026-11-21 10:00:00'),
            'passed',
            200
        );

        $this->actingAs($user)
            ->getJson(
                "/api/websites/{$website->id}/domain-ssl-history?per_page=1"
            )
            ->assertOk()
            ->assertJsonPath('website.id', $website->id)
            ->assertJsonPath('domain_checks.total', 2)
            ->assertJsonPath('domain_checks.data.0.id', $latestDomain->id)
            ->assertJsonPath('domain_checks.data.0.status', 'valid')
            ->assertJsonPath('ssl_checks.total', 2)
            ->assertJsonPath('ssl_checks.data.0.id', $latestSsl->id)
            ->assertJsonPath('ssl_checks.data.0.status', 'valid');
    }

    public function test_domain_ssl_filters_reject_invalid_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/domain-ssl-checks?domain_status=unknown-status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('domain_status');

        $this->actingAs($user)
            ->getJson('/api/domain-ssl-checks?ssl_status=unknown-status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ssl_status');

        $this->actingAs($user)
            ->getJson('/api/domain-ssl-checks?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}