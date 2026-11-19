<?php

namespace Tests\Feature;

use App\Models\Opd;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaleWebsiteScanRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function createScan(
        string $status,
        $startedAt,
        $completedAt = null
    ): Scan {
        $opd = Opd::create([
            'name' => 'OPD Recovery Test',
        ]);

        $website = $opd->websites()->create([
            'name' => 'Website Recovery Test',
            'url' => 'https://example.com',
            'is_active' => true,
            'monitoring_enabled' => false,
        ]);

        return $website->scans()->create([
            'status' => $status,
            'scheduled_for' => now()->subMinutes(5),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);
    }

    public function test_stale_running_scan_is_recovered_and_notified(): void
    {
        $user = User::factory()->create();

        $scan = $this->createScan(
            'running',
            now()->subMinutes(4)
        );

        $this->artisan('simonta:recover-stale-scans')
            ->expectsOutput('Scan running yang dipulihkan: 1')
            ->expectsOutput('Batas pemulihan: 3 menit.')
            ->assertExitCode(0);

        $scan->refresh();

        $this->assertSame('failed', $scan->status);

        $this->assertNotNull($scan->completed_at);

        $this->assertStringContainsString(
            'terlalu lama',
            $scan->error_message
        );

        // Pastikan notifikasi kegagalan dibuat.
        $this->assertSame(
            1,
            $user->notifications()->count()
        );

        $notification = $user->notifications()->firstOrFail();

        $this->assertSame(
            'scan_failed',
            $notification->data['type']
        );

        $this->assertSame(
            $scan->id,
            $notification->data['scan_id']
        );
    }

    public function test_recent_running_scan_is_not_recovered(): void
    {
        $user = User::factory()->create();

        $scan = $this->createScan(
            'running',
            now()->subMinutes(2)
        );

        $this->artisan('simonta:recover-stale-scans')
            ->expectsOutput('Scan running yang dipulihkan: 0')
            ->assertExitCode(0);

        $scan->refresh();

        $this->assertSame('running', $scan->status);

        $this->assertNull($scan->completed_at);

        $this->assertSame(
            0,
            $user->notifications()->count()
        );
    }

    public function test_completed_scan_is_not_modified_by_recovery(): void
    {
        $user = User::factory()->create();

        $completedAt = now()->subMinutes(3)->startOfSecond();

        $scan = $this->createScan(
            'completed',
            now()->subMinutes(5),
            $completedAt
        );

        $this->artisan('simonta:recover-stale-scans')
            ->expectsOutput('Scan running yang dipulihkan: 0')
            ->assertExitCode(0);

        $scan->refresh();

        $this->assertSame('completed', $scan->status);

        $this->assertEquals(
            $completedAt,
            $scan->completed_at
        );

        $this->assertSame(
            0,
            $user->notifications()->count()
        );
    }
}
