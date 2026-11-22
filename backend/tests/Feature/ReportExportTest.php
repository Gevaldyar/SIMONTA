<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\Technology;
use App\Models\User;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Carbon;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membuat OPD dan website untuk pengujian laporan.
     */
    private function createReportWebsite(
        string $opdName = 'OPD Report Test',
        string $websiteName = 'Website Report Test',
        string $url = 'https://report-test.example.com'
    ): Website {
        $opd = Opd::create([
            'name' => $opdName,
            'is_active' => true,
        ]);

        return $opd->websites()->create([
            'name' => $websiteName,
            'url' => $url,
            'is_active' => true,
            'monitoring_enabled' => false,
            'monitoring_interval_minutes' => 10,
        ]);
    }

    /**
     * Membuat scan beserta waktu pembuatan khusus.
     */
    private function createReportScan(
        Website $website,
        array $attributes = []
    ): Scan {
        $createdAt = $attributes['created_at'] ?? now();

        unset($attributes['created_at']);

        $scan = $website->scans()->create(array_merge([
            'status' => 'completed',
            'scheduled_for' => $createdAt,
            'started_at' => $createdAt,
            'completed_at' => $createdAt,
            'http_status_code' => 200,
            'response_time_ms' => 120,
            'final_url' => $website->url,
            'page_title' => 'Report Test Entry',
            'error_message' => null,
        ], $attributes));

        $scan->forceFill([
            'created_at' => $createdAt,
        ])->save();

        return $scan->fresh();
    }

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

    /**
     * Filter OPD hanya mengambil scan dari website OPD tersebut.
     */
    public function test_report_export_can_be_filtered_by_opd(): void
    {
        $user = User::factory()->create();

        $websiteA = $this->createReportWebsite(
            'Dinas Komunikasi',
            'Website Diskominfo',
            'https://diskominfo-report.example.com'
        );

        $websiteB = $this->createReportWebsite(
            'Dinas Kesehatan',
            'Website Dinkes',
            'https://dinkes-report.example.com'
        );

        $this->createReportScan($websiteA, [
            'page_title' => 'DATA_DISKOMINFO_REPORT',
        ]);

        $this->createReportScan($websiteB, [
            'page_title' => 'DATA_DINKES_REPORT',
        ]);

        $response = $this->actingAs($user)
            ->get(
                '/api/reports/scans/export?opd_id='
                . $websiteA->opd_id
            );

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            'DATA_DISKOMINFO_REPORT',
            $csv
        );

        $this->assertStringNotContainsString(
            'DATA_DINKES_REPORT',
            $csv
        );
    }

    /**
     * Filter website hanya mengambil scan website yang dipilih.
     */
    public function test_report_export_can_be_filtered_by_website(): void
    {
        $user = User::factory()->create();

        $websiteA = $this->createReportWebsite(
            'OPD Website Filter',
            'Website A',
            'https://website-a-report.example.com'
        );

        $websiteB = $this->createReportWebsite(
            'OPD Website Filter B',
            'Website B',
            'https://website-b-report.example.com'
        );

        $this->createReportScan($websiteA, [
            'page_title' => 'WEBSITE_A_REPORT',
        ]);

        $this->createReportScan($websiteB, [
            'page_title' => 'WEBSITE_B_REPORT',
        ]);

        $response = $this->actingAs($user)
            ->get(
                '/api/reports/scans/export?website_id='
                . $websiteA->id
            );

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            'WEBSITE_A_REPORT',
            $csv
        );

        $this->assertStringNotContainsString(
            'WEBSITE_B_REPORT',
            $csv
        );
    }

    /**
     * Kombinasi OPD dan website yang tidak sesuai
     * harus menghasilkan laporan kosong.
     */
    public function test_mismatched_opd_and_website_filters_return_no_records(): void
    {
        $user = User::factory()->create();

        $websiteA = $this->createReportWebsite(
            'OPD Alpha',
            'Website Alpha',
            'https://alpha-report.example.com'
        );

        $websiteB = $this->createReportWebsite(
            'OPD Beta',
            'Website Beta',
            'https://beta-report.example.com'
        );

        $this->createReportScan($websiteA, [
            'page_title' => 'ALPHA_RECORD',
        ]);

        $this->createReportScan($websiteB, [
            'page_title' => 'BETA_RECORD',
        ]);

        $response = $this->actingAs($user)
            ->get(
                '/api/reports/scans/export?'
                . http_build_query([
                    'opd_id' => $websiteA->opd_id,
                    'website_id' => $websiteB->id,
                ])
            );

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringNotContainsString(
            'ALPHA_RECORD',
            $csv
        );

        $this->assertStringNotContainsString(
            'BETA_RECORD',
            $csv
        );
    }

    /**
     * Filter tanggal menggunakan completed_at.
     * Scan yang belum selesai menggunakan created_at.
     */
    public function test_report_date_filter_uses_inspection_time(): void
    {
        $user = User::factory()->create();

        $website = $this->createReportWebsite();

        // Dibuat tanggal 8, tetapi pemeriksaan selesai tanggal 10.
        $this->createReportScan($website, [
            'created_at' => Carbon::parse('2026-11-08 10:00:00'),
            'completed_at' => Carbon::parse('2026-11-10 10:00:00'),
            'page_title' => 'COMPLETED_ON_NOVEMBER_10',
        ]);

        // Dibuat tanggal 10, tetapi selesai tanggal 11.
        $this->createReportScan($website, [
            'created_at' => Carbon::parse('2026-11-10 10:00:00'),
            'completed_at' => Carbon::parse('2026-11-11 10:00:00'),
            'page_title' => 'COMPLETED_ON_NOVEMBER_11',
        ]);

        // Belum selesai: gunakan created_at tanggal 10.
        $this->createReportScan($website, [
            'status' => 'queued',
            'started_at' => null,
            'completed_at' => null,
            'http_status_code' => null,
            'response_time_ms' => null,
            'created_at' => Carbon::parse('2026-11-10 12:00:00'),
            'page_title' => 'QUEUED_ON_NOVEMBER_10',
        ]);

        $response = $this->actingAs($user)
            ->get(
                '/api/reports/scans/export?'
                . http_build_query([
                    'date_from' => '2026-11-10',
                    'date_to' => '2026-11-10',
                ])
            );

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            'COMPLETED_ON_NOVEMBER_10',
            $csv
        );

        $this->assertStringContainsString(
            'QUEUED_ON_NOVEMBER_10',
            $csv
        );

        $this->assertStringNotContainsString(
            'COMPLETED_ON_NOVEMBER_11',
            $csv
        );
    }

    /**
     * Teknologi dan versinya harus dimasukkan ke CSV.
     */
    public function test_report_export_includes_detected_technologies(): void
    {
        $user = User::factory()->create();

        $website = $this->createReportWebsite();

        $scan = $this->createReportScan($website, [
            'page_title' => 'Technology Report Test',
        ]);

        $technology = Technology::create([
            'name' => 'PHP',
            'slug' => 'php-report-test',
            'category' => 'backend_language',
            'vendor' => 'PHP Group',
            'is_active' => true,
        ]);

        $scan->scanTechnologies()->create([
            'technology_id' => $technology->id,
            'version' => '8.3.2',
            'confidence' => 95,
        ]);

        $response = $this->actingAs($user)
            ->get('/api/reports/scans/export');

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            'PHP 8.3.2 (Confidence: 95%)',
            $csv
        );

        $this->assertStringContainsString(
            'Technology Report Test',
            $csv
        );
    }

    /**
     * Formula injection harus dinetralkan.
     */
    public function test_report_export_protects_against_csv_formula_injection(): void
    {
        $user = User::factory()->create();

        $website = $this->createReportWebsite();

        $this->createReportScan($website, [
            'page_title' => '=HYPERLINK("https://evil.example","Open")',
        ]);

        $response = $this->actingAs($user)
            ->get('/api/reports/scans/export');

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString(
            "'=HYPERLINK(",
            $csv
        );
    }
}