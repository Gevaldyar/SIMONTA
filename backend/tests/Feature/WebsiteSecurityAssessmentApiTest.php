<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WebsiteSecurityAssessmentApiTest extends TestCase
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

    private function createAssessmentScan(
        Website $website,
        string $status,
        ?int $score,
        Carbon $completedAt
    ): Scan {
        $assessment = [
            'version' => '1.0',
            'status' => $status,
            'risk_score' => $score,
            'finding_count' => 0,
            'findings' => [],
            'assessed_at' => $completedAt->toISOString(),
            'html_analyzed' => true,
        ];

        $scan = $website->scans()->create([
            'status' => 'completed',
            'scheduled_for' => $completedAt,
            'started_at' => $completedAt,
            'completed_at' => $completedAt,
            'http_status_code' => 200,
            'response_time_ms' => 120,
            'metadata' => [
                'security_assessment' => $assessment,
            ],
        ]);

        return $scan;
    }

    public function test_guest_cannot_view_security_assessments(): void
    {
        $this->getJson('/api/security-assessments')
            ->assertUnauthorized();
    }

    public function test_admin_can_view_latest_security_assessment_per_website(): void
    {
        $user = User::factory()->create();

        $website = $this->createWebsite('Portal OPD');

        // Scan terbaru dibuat lebih dahulu.
        $latestScan = $this->createAssessmentScan(
            $website,
            'high',
            60,
            Carbon::parse('2026-11-19 10:00:00')
        );

        // Scan lama dibuat belakangan sehingga ID lebih besar.
        $this->createAssessmentScan(
            $website,
            'low',
            0,
            Carbon::parse('2026-11-18 10:00:00')
        );

        $response = $this->actingAs($user)
            ->getJson('/api/security-assessments');

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('summary.high', 1)
            ->assertJsonPath('summary.low', 0)
            ->assertJsonPath('data.0.scan_id', $latestScan->id)
            ->assertJsonPath('data.0.assessment.status', 'high')
            ->assertJsonPath('data.0.assessment.risk_score', 60)
            ->assertJsonPath('data.0.website.name', 'Portal OPD');
    }

    public function test_admin_can_filter_assessments_by_risk_status(): void
    {
        $user = User::factory()->create();

        $highWebsite = $this->createWebsite('High Risk Website');
        $reviewWebsite = $this->createWebsite('Review Website');

        $this->createAssessmentScan(
            $highWebsite,
            'high',
            60,
            now()
        );

        $this->createAssessmentScan(
            $reviewWebsite,
            'review',
            35,
            now()->subHour()
        );

        $response = $this->actingAs($user)
            ->getJson('/api/security-assessments?risk_status=high');

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('summary.high', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assessment.status', 'high');
    }

    public function test_admin_can_filter_assessments_by_opd(): void
    {
        $user = User::factory()->create();

        $websiteA = $this->createWebsite('Website A');
        $websiteB = $this->createWebsite('Website B');

        $this->createAssessmentScan(
            $websiteA,
            'review',
            25,
            now()
        );

        $this->createAssessmentScan(
            $websiteB,
            'high',
            60,
            now()
        );

        $response = $this->actingAs($user)
            ->getJson(
                '/api/security-assessments?opd_id=' . $websiteA->opd_id
            );

        $response->assertOk()
            ->assertJsonPath('summary.total_websites', 1)
            ->assertJsonPath('data.0.website.name', 'Website A')
            ->assertJsonPath('data.0.assessment.status', 'review');
    }

    public function test_invalid_risk_status_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/security-assessments?risk_status=infected')
            ->assertUnprocessable();
    }
}