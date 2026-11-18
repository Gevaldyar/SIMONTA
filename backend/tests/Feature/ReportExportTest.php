<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_reports(): void
{
    $this->getJson('/api/reports/scans/export')
        ->assertUnauthorized();
}
    public function test_authenticated_user_can_download_csv_report(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/api/reports/scans/export');

        $response->assertOk();

        $response->assertHeader(
            'content-type',
            'text/csv; charset=UTF-8'
        );

        $this->assertStringContainsString(
            'Waktu Pemeriksaan',
            $response->streamedContent()
        );

        $this->assertStringContainsString(
            'Teknologi Terdeteksi',
            $response->streamedContent()
        );
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/reports/scans/export?status=unknown')
            ->assertUnprocessable();
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(
                '/api/reports/scans/export?date_from=2026-11-20&date_to=2026-11-19'
            )
            ->assertUnprocessable();
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(
                '/api/reports/scans/export?date_from=20-11-2026'
            )
            ->assertUnprocessable();
    }
}