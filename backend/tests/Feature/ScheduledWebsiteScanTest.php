<?php

namespace Tests\Feature;

use App\Jobs\RunWebsiteScanJob;
use App\Models\Opd;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledWebsiteScanTest extends TestCase
{
    use RefreshDatabase;

    
private function createWebsite(
    array $opdAttributes = [],
    array $websiteAttributes = []
) {
    // Buat nama OPD unik untuk setiap data pengujian.
    $opdNumber = Opd::withTrashed()->count() + 1;

    $opd = Opd::create(array_merge([
        'name' => 'Dinas Contoh ' . $opdNumber,
        'is_active' => true,
    ], $opdAttributes));

    return $opd->websites()->create(array_merge([
        'name' => 'Website Utama',
        'url' => 'https://example.com',
        'is_active' => true,
        'monitoring_enabled' => true,
        'monitoring_interval_minutes' => 10,
    ], $websiteAttributes));
}


    public function test_website_without_schedule_is_queued_for_first_scan(): void
    {
        Queue::fake();

        $website = $this->createWebsite();

        $this->artisan('simonta:dispatch-scheduled-scans')
            ->assertExitCode(0);

        // Scan pertama harus masuk antrean.
        $this->assertDatabaseHas('scans', [
            'website_id' => $website->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(
            RunWebsiteScanJob::class,
            1
        );

        // Jadwal berikutnya harus tersedia sekitar 10 menit.
$nextScan = $website->fresh()->next_scan_at;

$this->assertNotNull($nextScan);

$this->assertTrue(
    $nextScan->isAfter(now()->addMinutes(9)),
    'Jadwal berikutnya harus menggunakan default interval 10 menit.'
);

$this->assertTrue(
    $nextScan->isBefore(now()->addMinutes(11)),
    'Jadwal berikutnya tidak boleh melebihi 11 menit.'
);
    }

    public function test_future_scheduled_website_is_skipped(): void
    {
        Queue::fake();

        $website = $this->createWebsite();

        $website->forceFill([
            'next_scan_at' => now()->addMinutes(30),
        ])->save();

        $this->artisan('simonta:dispatch-scheduled-scans')
            ->assertExitCode(0);

        $this->assertDatabaseCount('scans', 0);

        Queue::assertNothingPushed();
    }

    public function test_inactive_and_disabled_monitoring_websites_are_skipped(): void
    {
        Queue::fake();

        $inactiveWebsite = $this->createWebsite(
            ['is_active' => false]
        );

        $disabledMonitoring = $this->createWebsite(
            [],
            ['monitoring_enabled' => false]
        );

        $inactiveOpdWebsite = $this->createWebsite(
            ['name' => 'Dinas Tidak Aktif', 'is_active' => false]
        );

        $this->artisan('simonta:dispatch-scheduled-scans')
            ->assertExitCode(0);

        $this->assertDatabaseCount('scans', 0);

        Queue::assertNothingPushed();
    }

    public function test_website_with_pending_scan_is_not_queued_again(): void
    {
        Queue::fake();

        $website = $this->createWebsite();

        $website->forceFill([
            'next_scan_at' => now()->subMinutes(5),
        ])->save();

        // Ada scan yang masih menunggu.
        $website->scans()->create([
            'status' => 'queued',
            'scheduled_for' => now()->subMinutes(5),
        ]);

        $this->artisan('simonta:dispatch-scheduled-scans')
            ->assertExitCode(0);

        // Tidak membuat scan tambahan.
        $this->assertDatabaseCount('scans', 1);

        Queue::assertNothingPushed();
    }

    public function test_next_scan_schedule_respects_website_interval(): void
    {
        Queue::fake();

        $website = $this->createWebsite(
            [],
            [
                'monitoring_interval_minutes' => 10,
            ]
        );

        $website->forceFill([
            'next_scan_at' => now()->subMinutes(2),
        ])->save();

        $this->artisan('simonta:dispatch-scheduled-scans')
            ->assertExitCode(0);

        $nextScan = $website->fresh()->next_scan_at;

        // Interval berikutnya harus 10 menit.
$this->assertTrue(
    $nextScan->isAfter(now()->addMinutes(9))
);

        $this->assertDatabaseHas('scans', [
            'website_id' => $website->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(
            RunWebsiteScanJob::class,
            1
        );
    }

public function test_scheduler_respects_all_supported_minute_intervals(): void
{
    Queue::fake();

    $baseTime = now()->startOfMinute();

    $this->travelTo($baseTime);

    $intervals = [1, 5, 10, 30];
    $websites = [];

    foreach ($intervals as $interval) {
        $website = $this->createWebsite(
            [],
            [
                'url' => "https://interval-{$interval}.example.com",
                'monitoring_interval_minutes' => $interval,
            ]
        );

        $website->forceFill([
            'next_scan_at' => $baseTime->copy()->subMinute(),
        ])->save();

        $websites[$interval] = $website;
    }

    $this->artisan('simonta:dispatch-scheduled-scans')
        ->assertExitCode(0);

    foreach ($websites as $interval => $website) {
        $expectedTime = $baseTime
            ->copy()
            ->addMinutes($interval);

        $this->assertTrue(
            $website->fresh()->next_scan_at->equalTo($expectedTime),
            "Interval {$interval} menit tidak diterapkan dengan benar."
        );

        $this->assertDatabaseHas('scans', [
            'website_id' => $website->id,
            'status' => 'queued',
        ]);
    }

    Queue::assertPushed(RunWebsiteScanJob::class, 4);

    $this->travelBack();
}

}
