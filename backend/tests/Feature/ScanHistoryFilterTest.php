<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScanHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function createWebsite()
    {
        $opd = Opd::create([
            'name' => 'Dinas Riwayat Pengujian',
        ]);

        return $opd->websites()->create([
            'name' => 'Website Riwayat',
            'url' => 'https://example.com',
        ]);
    }

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    public function test_scan_history_can_be_filtered_by_date_range(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite();

        $oldScan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        $recentScan = $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        DB::table('scans')
            ->where('id', $oldScan->id)
            ->update([
                'created_at' => '2026-11-10 10:00:00',
            ]);

        DB::table('scans')
            ->where('id', $recentScan->id)
            ->update([
                'created_at' => '2026-11-18 10:00:00',
            ]);

        $this->getJson(
            '/api/scans?date_from=2026-11-18&date_to=2026-11-18'
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $recentScan->id);
    }

    public function test_scan_history_can_combine_status_and_website_filters(): void
    {
        $this->loginAsAdmin();

        $website = $this->createWebsite();

        $website->scans()->create([
            'status' => 'completed',
            'http_status_code' => 200,
        ]);

        $website->scans()->create([
            'status' => 'failed',
            'error_message' => 'Timeout',
        ]);

        $this->getJson(
            "/api/scans?status=completed&website_id={$website->id}"
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->loginAsAdmin();

        $this->getJson(
            '/api/scans?date_from=2026-11-18&date_to=2026-11-10'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_invalid_date_format_is_rejected(): void
    {
        $this->loginAsAdmin();

        $this->getJson('/api/scans?date_from=18-11-2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from']);
    }
}
