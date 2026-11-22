<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SecurityHeadersAuditApiTest extends TestCase
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
            'url' => 'https://example.com',
            'is_active' => true,
            'monitoring_enabled' => false,
            'monitoring_interval_minutes' => 10,
        ]);
    }

    private function createAuditScan(
        Website $website,
        string $status,
        int $score,
        Carbon $completedAt
    ): Scan {
        $audit = [
            'version' => '1.0.0',
            'status' => $status,
            'score' => $score,
            'max_score' => 100,
            'applicable_max_score' => 100,
            'finding_count' => 1,
            'headers_present' => 5,
            'headers_checked' => 6,
            'checked_at' => $completedAt->toISOString(),
            'findings' => [
                [
                    'key' => 'content-security-policy',
                    'label' => 'Content Security Policy',
                    'status' => 'partial',
                    'score' => 15,
                    'max_score' => 30,
                    'summary' => 'CSP belum mencakup seluruh direktif yang diperiksa.',
                    'observed' => [
                        "frame-ancestors 'self'",
                    ],
                    'recommendation' => 'Evaluasi penambahan direktif CSP.',
                ],
            ],
        ];

        return $website->scans()->create([
            'status' => 'completed',
            'scheduled_for' => $completedAt,
            'started_at' => $completedAt,
            'completed_at' => $completedAt,
            'http_status_code' => 200,
            'response_time_ms' => 120,
            'metadata' => [
                'security_headers_audit' => $audit,
                'security_headers' => [
                    'x-content-type-options' => ['nosniff'],
                ],
                'tls_certificate' => [
                    'status' => 'valid',
                    'days_remaining' => 169,
                ],
            ],
        ]);
    }

    public function test_guest_cannot_view_security_headers_audits(): void
    {
        $this->getJson('/api/security-headers-audits')
            ->assertUnauthorized();
    }

    public function test_admin_can_view_latest_audit_per_website(): void
    {
        $user = User::factory()->create();

        $website = $this->createWebsite('Portal OPD');

        // Audit terbaru dibuat lebih dahulu.
        $latestScan = $this->createAuditScan(
            $website,
            'needs_review',
            60,
            Carbon::parse('2026-11-20 10:00:00')
        );

        // Audit lama dibuat belakangan sehingga ID lebih besar.
        $this->createAuditScan(
            $website,
            'strong',
            100,
            Carbon::parse('2026-11-19 10:00:00')
        );

        $response = $this->actingAs($user)
            ->getJson('/api/security-headers-audits');

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('summary.needs_review', 1)
            ->assertJsonPath('summary.strong', 0)
            ->assertJsonPath('data.0.scan_id', $latestScan->id)
            ->assertJsonPath('data.0.audit.status', 'needs_review')
            ->assertJsonPath('data.0.audit.score', 60)
            ->assertJsonPath('data.0.website.name', 'Portal OPD')
            ->assertJsonPath(
                'data.0.security_headers.x-content-type-options.0',
                'nosniff'
            )
            ->assertJsonPath(
                'data.0.tls_certificate.status',
                'valid'
            );
    }

    public function test_admin_can_filter_audits_by_status(): void
    {
        $user = User::factory()->create();

        $weakWebsite = $this->createWebsite('Weak Website');
        $strongWebsite = $this->createWebsite('Strong Website');

        $this->createAuditScan(
            $weakWebsite,
            'weak',
            30,
            Carbon::parse('2026-11-20 10:00:00')
        );

        $this->createAuditScan(
            $strongWebsite,
            'strong',
            100,
            Carbon::parse('2026-11-20 11:00:00')
        );

        $response = $this->actingAs($user)
            ->getJson('/api/security-headers-audits?audit_status=weak');

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('summary.weak', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.audit.status', 'weak')
            ->assertJsonPath('data.0.website.name', 'Weak Website');
    }

    public function test_admin_can_filter_audits_by_opd(): void
    {
        $user = User::factory()->create();

        $websiteA = $this->createWebsite('Website A');
        $websiteB = $this->createWebsite('Website B');

        $this->createAuditScan(
            $websiteA,
            'needs_review',
            60,
            Carbon::parse('2026-11-20 10:00:00')
        );

        $this->createAuditScan(
            $websiteB,
            'strong',
            100,
            Carbon::parse('2026-11-20 11:00:00')
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/security-headers-audits?opd_id=' . $websiteA->opd_id
            );

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('data.0.website.name', 'Website A')
            ->assertJsonPath('data.0.audit.status', 'needs_review');
    }

    public function test_admin_can_filter_audits_by_website(): void
    {
        $user = User::factory()->create();

        $websiteA = $this->createWebsite('Website A');
        $websiteB = $this->createWebsite('Website B');

        $this->createAuditScan(
            $websiteA,
            'moderate',
            75,
            Carbon::parse('2026-11-20 10:00:00')
        );

        $this->createAuditScan(
            $websiteB,
            'weak',
            30,
            Carbon::parse('2026-11-20 11:00:00')
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/security-headers-audits?website_id=' . $websiteA->id
            );

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.website.name', 'Website A')
            ->assertJsonPath('data.0.audit.status', 'moderate');
    }

    public function test_invalid_audit_status_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/security-headers-audits?audit_status=infected')
            ->assertUnprocessable();
    }

    public function test_audit_pagination_limit_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/security-headers-audits?per_page=101')
            ->assertUnprocessable();
    }
}